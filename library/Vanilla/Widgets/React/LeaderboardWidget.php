<?php
/**
 * @author Maneesh Chiba <maneesh.chiba@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Vanilla\Widgets\React;

use Garden\Schema\Schema;
use Gdn;
use Vanilla\Dashboard\Models\UserLeaderQuery;
use Vanilla\Dashboard\UserLeaderService;
use Vanilla\Dashboard\UserPointsModel;
use Vanilla\Forms\ApiFormChoices;
use Vanilla\Forms\FieldMatchConditional;
use Vanilla\Forms\FormOptions;
use Vanilla\Forms\SchemaForm;
use Vanilla\Forms\StaticFormChoices;
use Vanilla\Forum\Controllers\Api\DiscussionsApiIndexSchema;
use Vanilla\Models\UserFragmentSchema;
use Vanilla\Site\SiteSectionModel;
use Vanilla\Utility\SchemaUtils;
use Vanilla\Web\JsInterpop\AbstractReactModule;
use Vanilla\Widgets\HomeWidgetContainerSchemaTrait;

/**
 * Widget with the users having the top points.
 */
class LeaderboardWidget extends AbstractReactModule implements ReactWidgetInterface, CombinedPropsWidgetInterface
{
    use CombinedPropsWidgetTrait;
    use HomeWidgetContainerSchemaTrait;

    /** @var UserLeaderService */
    private $userLeaderService;

    /** @var \UserModel */
    private $userModel;

    /**
     * DI.
     *
     * @param UserLeaderService $userLeaderService
     * @param \UserModel $userModel
     */
    public function __construct(UserLeaderService $userLeaderService, \UserModel $userModel)
    {
        parent::__construct();
        $this->userLeaderService = $userLeaderService;
        $this->userModel = $userModel;
    }

    /**
     * Map a user into a widget item.
     *
     * @param array $user A full user from the database + UserPoints data.
     *
     * @return array
     */
    private function mapUserToWidgetItem(array $user): array
    {
        $points = $user["Points"] ?? ($user["points"] ?? 0);
        $user = UserFragmentSchema::normalizeUserFragment($user);

        return [
            "user" => $user,
            "points" => $points,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getProps(): ?array
    {
        //filter by category or siteSection (subcommunity)
        $categoryID = null;
        $siteSectionID = null;

        if ($this->props["apiParams"]["filter"] === "category" && $this->props["apiParams"]["categoryID"]) {
            $categoryID = $this->props["apiParams"]["categoryID"];
        } elseif ($this->props["apiParams"]["filter"] === "siteSection" && $this->props["apiParams"]["siteSectionID"]) {
            $siteSectionID = $this->props["apiParams"]["siteSectionID"];
        }

        $query = new UserLeaderQuery(
            $this->props["apiParams"]["slotType"],
            $categoryID,
            $siteSectionID,
            $this->props["apiParams"]["limit"],
            $this->props["apiParams"]["includedRoleIDs"] ?? null,
            $this->props["apiParams"]["excludedRoleIDs"] ?? null,
            $this->props["apiParams"]["leaderboardType"] ?? null
        );
        $users = $this->userLeaderService->getLeaders($query);
        if (count($users) === 0) {
            return null;
        }
        $users = array_map([$this, "mapUserToWidgetItem"], $users);
        $result = array_merge($this->props, [
            "leaders" => $users,
        ]);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function getWidgetSchema(): Schema
    {
        $categoryIDSchema = Schema::parse([
            "type" => "integer",
            "default" => null,
            "description" => "The category user points should be calculated in.",
            "x-control" => DiscussionsApiIndexSchema::getCategoryIDFormOptions(
                new FieldMatchConditional(
                    "apiParams.filter",
                    Schema::parse([
                        "type" => "string",
                        "const" => "category",
                    ])
                )
            ),
        ]);

        $includedRolesIDsSchema = Schema::parse([
            "x-no-hydrate" => true,
            "description" => "Roles to include to the leaderboard.",
            "type" => "array",
            "default" => [],
            "x-control" => SchemaForm::dropDown(
                new FormOptions(t("Included Roles"), t("Roles to include to the leaderboard."), t("All")),
                new ApiFormChoices("/api/v2/roles", "/api/v2/roles/%s", "roleID", "name"),
                null,
                true
            ),
        ]);

        $excludedRolesIDsSchema = Schema::parse([
            "x-no-hydrate" => true,
            "description" => "Roles to exclude from the leaderboard.",
            "type" => "array",
            "default" => [],
            "x-control" => SchemaForm::dropDown(
                new FormOptions(t("Excluded Roles"), t("Roles to exclude from the leaderboard."), t("All")),
                new ApiFormChoices("/api/v2/roles", "/api/v2/roles/%s", "roleID", "name"),
                null,
                true
            ),
        ]);

        $filterEnum = ["none", "category"];
        $filterStaticFormChoices = ["none" => "None", "category" => "Category"];

        $siteSectionModel = \Gdn::getContainer()->get(SiteSectionModel::class);
        $siteSectionIDSchema = $siteSectionModel->getSiteSectionFormOption(
            new FieldMatchConditional(
                "apiParams.filter",
                Schema::parse([
                    "type" => "string",
                    "const" => "siteSection",
                ])
            )
        );

        // include subcommunities filter
        if ($siteSectionIDSchema !== null) {
            $filterEnum[] = "siteSection";
            $filterStaticFormChoices["siteSection"] = "Subcommunity";
        }

        $filterBySchema = Schema::parse([
            "enum" => $filterEnum,
            "description" => "Choose filter type.",
            "default" => "none",
            "type" => "string",
            "x-control" => SchemaForm::dropDown(
                new FormOptions("Filter By"),
                new StaticFormChoices($filterStaticFormChoices)
            ),
        ]);

        $apiParams = [
            "slotType?" => UserPointsModel::slotTypeSchema(),
            "leaderboardType?" => UserPointsModel::leaderboardTypeSchema(),
            "filter?" => $filterBySchema,
            "categoryID?" => $categoryIDSchema,
            "siteSectionID?" => $siteSectionIDSchema,
            "limit?" => UserPointsModel::limitSchema(),
            "includedRoleIDs?" => $includedRolesIDsSchema,
            "excludedRoleIDs?" => $excludedRolesIDsSchema,
        ];

        $widgetSpecificSchema = Schema::parse([
            "apiParams?" => Schema::parse($apiParams),
        ]);

        return SchemaUtils::composeSchemas(
            self::widgetTitleSchema("All Time Leaders"),
            self::widgetSubtitleSchema("subtitle"),
            self::widgetDescriptionSchema(),
            $widgetSpecificSchema,
            self::containerOptionsSchema("containerOptions")
        );
    }

    /**
     * @inheritDoc
     */
    public static function getComponentName(): string
    {
        return "LeaderboardWidget";
    }

    /**
     * @inheritDoc
     */
    public static function getWidgetID(): string
    {
        return "leaderboard";
    }

    /**
     * @inheritDoc
     */
    public static function getWidgetName(): string
    {
        return "Leaderboard";
    }

    /**
     * @return string
     */
    public static function getWidgetIconPath(): string
    {
        return "/applications/dashboard/design/images/widgetIcons/leaderboard.svg";
    }
}
