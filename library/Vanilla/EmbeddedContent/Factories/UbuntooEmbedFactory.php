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

    const SHORT_DOMAIN = "ubuntoo.com";

    const PRIMARY_DOMAINS = ["ubuntoo.com"];

    //const OEMBED_URL_BASE = "https://app.ubuntoo.com";

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
        return "`^/s|k/`";
    }

    /**
     * Use the page scraper to scrape page data.
     *
     * @inheritdoc
     */
    public function createEmbedForUrl(string $url): AbstractEmbed {
        return $this->parseType($url);
    }

    /**
     * Form graphql query based on type.
     *
     * @param string $contentUrl
     * @return LinkEmbed
     *
     * @throws \Garden\Schema\ValidationException If there's not enough / incorrect data to make an embed.
     * @throws \Exception If the scrape fails.
     */
    private function parseType(string $contentUrl): LinkEmbed {

        $solutionQuery = <<<'SGQL'
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
        SGQL;

        $knowledgeQuery = <<<'KGQL'
            query knowledgeByUrl($url: String!) {
                knowledgeByUrl(url: $url) {
                    id
                    url
                    title
                    body
                    name
                    summary
                    partOf
                    status
                    date
                    bannerImageUrl
                    thumbImageUrl
                    keywordTags
                    authors {
                      name
                      title
                      image
                      shortBio
                    }
                    categories
                    industries
                    greenhouses
                    sourceUrl
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
        KGQL;

        $path = parse_url($contentUrl, PHP_URL_PATH);
        $contentType = explode('/', $path)[1];

        //$shortDesc = '';
        //$photoUrl = '';

        if ($contentType == 'solutions' || $contentType == 's') {
            $content = $this->queryGraphQL($contentUrl, 'solutionByUrl', $solutionQuery);


            $shortDesc = $content->shortBio;
            $photoUrl = $content->companyImageUrl;
        } else {
            $content = $this->queryGraphQL($contentUrl, 'knowledgeByUrl', $knowledgeQuery);
            $shortDesc = $content->title;
            $photoUrl = $content->thumbImageUrl;
        }

        return $this->constructLinkEmbed($contentUrl, $content, $shortDesc, $photoUrl);
    }

    /**
     * Query graphql.
     *
     * @param string $contentUrl
     * @param string $operationName
     * @param string $query
     * @return LinkEmbed
     *
     * @throws \Garden\Schema\ValidationException If there's not enough / incorrect data to make an embed.
     * @throws \Exception If the scrape fails.
     */
    private function queryGraphQL(string $contentUrl, string $operationName, string $query): object {

        $graphqlEndpoint = getenv('REACT_APP_API_HOST') .'/api/graphql';

        $client = new \GuzzleHttp\Client();

        $variables = [
            'url' => basename($contentUrl)
        ];

        $response = $client->request('POST', $graphqlEndpoint, [
          //'headers' => [
            // include any auth tokens here
          //],
          'json' => [
            'operationName' => $operationName,
            'variables' => $variables,
            'query' => $query
          ],
        ]);


        $json = $response->getBody()->getContents();
        $body = json_decode($json);
        $content = $body->data->$operationName;

        return $content;
    }


    /**
     * Construct a linkembed using provided data fields.
     *
     * @param string $contentUrl
     * @param object $content
     * @param string $shortDesc
     * @param string $photoUrl
     * @return LinkEmbed
     */
    private function constructLinkEmbed(string $contentUrl, object $content, string $shortDesc, string $photoUrl): LinkEmbed {
        $data = [
            "embedType" => LinkEmbed::TYPE,
            "url" => $contentUrl,
            "name" => $content->name,
            "body" => $shortDesc,
            "photoUrl" => $photoUrl,
        ];

        $linkEmbed = new LinkEmbed($data);
        $linkEmbed->setCacheable(true);


        return $linkEmbed;
    }
}
