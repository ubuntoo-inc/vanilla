<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\EmbeddedContent\Factories;

use Garden\Http\HttpClient;
use Vanilla\EmbeddedContent\AbstractEmbed;
use Vanilla\EmbeddedContent\AbstractEmbedFactory;
use Vanilla\EmbeddedContent\Embeds\LinkEmbed;
use Vanilla\PageScraper;

/**
 * Factory for YouTubeEmbed.
 */
class UbuntooEmbedFactory extends AbstractEmbedFactory {

    const SHORT_DOMAIN = "app.ubuntoo.com";

    const PRIMARY_DOMAINS = ["app.ubuntoo.com"];

    const OEMBED_URL_BASE = "https://app.ubuntoo.com";

    /** @var HttpClient */
    private $httpClient;

    /** @var PageScraper */
    private $pageScraper;

    /**
     * DI.
     *
     * @param HttpClient $httpClient
     */
    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
    }

    /**
     * @inheritdoc
     */
    protected function getSupportedDomains(): array {
        $domains = self::PRIMARY_DOMAINS;
        $domains[] = self::SHORT_DOMAIN;
        return $domains;
    }

    /**
     * @inheritdoc
     */
    protected function getSupportedPathRegex(string $domain): string {
        return "`^/s(olutions)/`";
    }

    /**
     * Use the page scraper to scrape page data.
     *
     * @inheritdoc
     */
    public function createEmbedForUrl(string $url): AbstractEmbed {
        return $this->queryGraphQL($url);
    }

    /**
     * Scrape an HTML page.
     *
     * @param string $url
     * @return LinkEmbed
     *
     * @throws \Garden\Schema\ValidationException If there's not enough / incorrect data to make an embed.
     * @throws \Exception If the scrape fails.
     */
    private function queryGraphQL(string $solutionUrl): LinkEmbed {

        $query = <<<'GQL'
        query solutionByUrl($url: String!) {
                solutionByUrl (url: $url) {
                    id
                    url
                    companyUrl
                    name
                    shortBio
                    about
                    companyName
                    status
                    partOf
                    stageOfDevelopment
                    organizationType
                    detailedSpecs
                    createdAt
                    updatedAt
                    yearFounded
                    location
                    companyImageUrl
                    bannerImageUrl
                    thumbImageUrl
                    stages {
                      id
                      stage
                    }
                    topics
                    seekings
                    valueChainImpacts
                    innovators
                    keywordTags
                    keyContacts {
                      name
                      title
                      image
                      shortBio
                    }
                    greenhouses
                    newsItems {
                      id
                      title
                      source
                      description
                      pageContent
                      location
                      partOf
                      status
                      newsDate
                      externalUrl
                      featuredImageUrl
                      keywordTags
                      topics
                      stages
                      greenhouses
                    }
                    comments {
                        id
                        body
                        createdDate
                        firstName
                        lastName
                        email
                    }
                }
            }
        GQL;

        $graphqlEndpoint = 'https://app.ubuntoo.com/api/graphql';

        $client = new \GuzzleHttp\Client();

        $variables = [
            'url' => basename($solutionUrl)
        ];

        $response = $client->request('POST', $graphqlEndpoint, [
          //'headers' => [
            // include any auth tokens here
          //],
          'json' => [
            'operationName' => 'solutionByUrl',
            'variables' => $variables,
            'query' => $query
          ],
        ]);


        $json = $response->getBody()->getContents();
        $body = json_decode($json);
        $solution = $body->data->solutionByUrl;

        $data = [
            "embedType" => LinkEmbed::TYPE,
            "url" => $solutionUrl,
            "name" => $solution->name,
            "body" => $solution->shortBio,
            "photoUrl" => $solution->bannerImageUrl,
        ];

        $linkEmbed = new LinkEmbed($data);
        $linkEmbed->setCacheable(true);


        return $linkEmbed;
    }


    /**
     * Given a Ubuntoo Solution URL, extract its slug.
     *
     * @param string $url
     * @return string|null
     */
    private function solutionFromUrl(string $url): ?string {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === self::SHORT_DOMAIN) {
            $path = parse_url($url, PHP_URL_PATH) ?? "";
            return preg_match("`^/?(?<videoID>[\w-]{11})`", $path, $matches) ? $matches["videoID"] : null;
        } else {
            $parameters = [];
            parse_str(parse_url($url, PHP_URL_QUERY) ?? "", $parameters);
            return $parameters["v"] ?? null;
        }

        return null;
    }
}
