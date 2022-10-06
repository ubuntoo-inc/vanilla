<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\EmbeddedContent\Factories;

use Garden\Http\HttpClient;
use Vanilla\EmbeddedContent\AbstractEmbed;
use Vanilla\EmbeddedContent\AbstractEmbedFactory;
use Vanilla\EmbeddedContent\Embeds\UbuntooEmbed;
use Vanilla\EmbeddedContent\Embeds\LinkEmbed;

/**
 * Factory for YouTubeEmbed.
 */
class UbuntooEmbedFactory extends AbstractEmbedFactory {

    const SHORT_DOMAIN = "app.ubuntoo.com";

    const PRIMARY_DOMAINS = ["app.ubuntoo.com"];

    const OEMBED_URL_BASE = "https://app.ubuntoo.com";

    /** @var HttpClient */
    private $httpClient;

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
        return "`^/s/`";
    }

    /**
     * Use the page scraper to scrape page data.
     *
     * @inheritdoc
     */
    public function createEmbedForUrl(string $url): AbstractEmbed {
        $videoID = $this->solutionFromUrl($url);

        $response = $this->httpClient->get(
            self::OEMBED_URL_BASE,
            ["url" => $url]
        );

        // Example Response JSON
        // phpcs:disable Generic.Files.LineLength
        // {
        //     "type": "video",
        //     "provider_url": "https://www.youtube.com/",
        //     "thumbnail_url": "https://i.ytimg.com/vi/hCeNC1sfEMM/hqdefault.jpg",
        //     "author_name": "2ndJerma",
        //     "author_url": "https://www.youtube.com/channel/UCL7DDQWP6x7wy0O6L5ZIgxg",
        //     "version": "1.0",
        //     "provider_name": "Ubuntoo",
        //     "html": "<iframe width=\"480\" height=\"270\" src=\"https://www.youtube.com/embed/hCeNC1sfEMM?feature=oembed\" frameborder=\"0\" allow=\"accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>",
        //     "thumbnail_height": 360,
        //     "thumbnail_width": 480,
        //     "title": "The Best of 2018",
        //     "width": 480,
        //     "height": 270
        // }
        // phpcs:enable Generic.Files.LineLength

        $parameters = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?? "", $parameters);

        $data = [
            "embedType" => LinkEmbed::TYPE,
            "url" => "https://app.ubuntoo.com/solutions/sippy",
            "name" => "Sippy",
            "body" => "Automatic beverage dispenser for restaurants and fast food companies",
            "photoUrl" => "https://d2jb9xtezsiuu6.cloudfront.net/uploads/solution/company_image/8388/company_image_T20221005054235.jpeg",
        ];

        $linkEmbed = new LinkEmbed($data);
        $linkEmbed->setCacheable(!empty($scraped['isCacheable']));

        return $linkEmbed;
    }

    /**
     * Get a YouTube URL's time value and convert it to seconds (e.g. 2m8s to 128).
     *
     * @param string $url
     * @return int|null
     */
    private function startTime(string $url): ?int {
        $parameters = [];
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? "", $parameters);

        if (!is_string($fragment) && empty($parameters)) {
            return null;
        }

        if (preg_match("/t=(?P<start>\d+)/", $fragment, $timeParts)) {
            return (int)$timeParts["start"];
        }

        if (preg_match("/^(?:(?P<ticks>\d+)|(?:(?P<minutes>\d*)m)?(?:(?P<seconds>\d*)s)?)$/", $parameters["t"] ?? "", $timeParts)) {
            if (array_key_exists("ticks", $timeParts) && $timeParts["ticks"] !== "") {
                return $timeParts["ticks"];
            } else {
                $minutes = $timeParts["minutes"] ? (int)$timeParts["minutes"] : 0;
                $seconds = $timeParts["seconds"] ? (int)$timeParts["seconds"] : 0;
                return ($minutes * 60) + $seconds;
            }
        }

        return null;
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
