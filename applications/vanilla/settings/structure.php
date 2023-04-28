<?php use Vanilla\Dashboard\Models\RecordStatusModel;

if (!defined("APPLICATION")) {
    exit();
}
/**
 * Vanilla database structure.
 *
 * Called by VanillaHooks::setup() to update database upon enabling app.
 *
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 * @since 2.0
 * @package Vanilla
 */

if (!isset($Drop)) {
    $Drop = false;
}

if (!isset($Explicit)) {
    $Explicit = false;
}

$SQL = Gdn::database()->sql();
$Construct = Gdn::database()->structure();
$Px = $Construct->databasePrefix();

$captureOnly = Gdn::database()->structure()->CaptureOnly;

$Construct->table("Category");
$CategoryExists = $Construct->tableExists();
$CountCategoriesExists = $Construct->columnExists("CountCategories");
$PermissionCategoryIDExists = $Construct->columnExists("PermissionCategoryID");
$HeroImageExists = $Construct->columnExists("HeroImage");

$LastDiscussionIDExists = $Construct->columnExists("LastDiscussionID");

$CountAllDiscussionsExists = $Construct->columnExists("CountAllDiscussions");
$CountAllCommentsExists = $Construct->columnExists("CountAllComments");

$config = Gdn::config();
// Rename the remnants of the Hero Image plugin.
if ($HeroImageExists) {
    $config->remove("EnabledPlugins.heroimage");
    $Construct->table("Category");
    $Construct->renameColumn("HeroImage", "BannerImage");
}

if ($configBannerImage = Gdn::config("Garden.HeroImage")) {
    $config->set("Garden.BannerImage", $configBannerImage);
    $config->remove("Garden.HeroImage");
}

// Fix the casening of the Rich post format.
// For a short period, lowercase rich format values were being saved into the config.
if ($config->get("Garden.InputFormatter") === "rich") {
    $config->set("Garden.InputFormatter", "Rich");
}

if ($config->get("Garden.MobileInputFormatter") === "rich") {
    $config->set("Garden.MobileInputFormatter", "Rich");
}

$Construct
    ->primaryKey("CategoryID")
    ->column("ParentCategoryID", "int", true, "key")
    ->column("TreeLeft", "int", true)
    ->column("TreeRight", "int", true)
    ->column("Depth", "int", "0")
    ->column("CountCategories", "int", "0")
    ->column("CountDiscussions", "int", "0")
    ->column("CountAllDiscussions", "int", "0")
    ->column("CountComments", "int", "0")
    ->column("CountAllComments", "int", "0")
    ->column("LastCategoryID", "int", "0")
    ->column("DateMarkedRead", "datetime", null)
    ->column("AllowDiscussions", "tinyint", "1")
    ->column("Archived", "tinyint(1)", "0")
    ->column("CanDelete", "tinyint", "1")
    ->column("Name", "varchar(255)")
    ->column("UrlCode", "varchar(255)", false, "unique")
    ->column("Description", "varchar(1000)", true)
    ->column("Sort", "int", true)
    ->column("CssClass", "varchar(50)", true)
    ->column("Photo", "varchar(767)", true)
    ->column("BannerImage", "varchar(255)", true)
    ->column("PermissionCategoryID", "int", "-1") // default to root.
    ->column("PointsCategoryID", "int", "0") // default to global.
    ->column("HideAllDiscussions", "tinyint(1)", "0")
    ->column("DisplayAs", ["Categories", "Discussions", "Flat", "Heading", "Default"], "Discussions")
    ->column("InsertUserID", "int", false, "key")
    ->column("UpdateUserID", "int", true)
    ->column("DateInserted", "datetime")
    ->column("DateUpdated", "datetime")
    ->column("LastCommentID", "int", null)
    ->column("LastDiscussionID", "int", null)
    ->column("LastDateInserted", "datetime", null)
    ->column("AllowedDiscussionTypes", "varchar(255)", null)
    ->column("DefaultDiscussionType", "varchar(10)", null)
    ->column("Featured", "tinyint", "0")
    ->column("SortFeatured", "int", "0", "index")
    ->set($Explicit, $Drop);

$RootCategoryInserted = false;
if ($SQL->getWhere("Category", ["CategoryID" => -1])->numRows() == 0) {
    $SQL->insert("Category", [
        "CategoryID" => -1,
        "TreeLeft" => 1,
        "TreeRight" => 4,
        "InsertUserID" => 1,
        "UpdateUserID" => 1,
        "DateInserted" => Gdn_Format::toDateTime(),
        "DateUpdated" => Gdn_Format::toDateTime(),
        "Name" => "Root",
        "UrlCode" => "",
        "Description" => "Root of category tree. Users should never see this.",
        "PermissionCategoryID" => -1,
        "DisplayAs" => "Categories",
    ]);
    $RootCategoryInserted = true;
}

if ($Drop || !$CategoryExists) {
    $SQL->insert("Category", [
        "ParentCategoryID" => -1,
        "TreeLeft" => 2,
        "TreeRight" => 3,
        "Depth" => 1,
        "InsertUserID" => 1,
        "UpdateUserID" => 1,
        "DateInserted" => Gdn_Format::toDateTime(),
        "DateUpdated" => Gdn_Format::toDateTime(),
        "Name" => "General",
        "UrlCode" => "general",
        "Description" => "General discussions",
        "PermissionCategoryID" => -1,
    ]);
} elseif ($CategoryExists && !$PermissionCategoryIDExists) {
    if (!c("Garden.Permissions.Disabled.Category")) {
        // Existing installations need to be set up with per/category permissions.
        $SQL->update("Category")
            ->set("PermissionCategoryID", "CategoryID", false)
            ->put();
        $SQL->update("Permission")
            ->set("JunctionColumn", "PermissionCategoryID")
            ->where("JunctionColumn", "CategoryID")
            ->put();
    }
}

if ($CategoryExists) {
    CategoryModel::instance()->rebuildTree();
    CategoryModel::instance()->recalculateTree();
}

// Construct the discussion table.
$Construct->table("Discussion");
$DiscussionExists = $Construct->tableExists();
$FirstCommentIDExists = $Construct->columnExists("FirstCommentID");
$BodyExists = $Construct->columnExists("Body");
$LastCommentIDExists = $Construct->columnExists("LastCommentID");
$LastCommentUserIDExists = $Construct->columnExists("LastCommentUserID");
$CountBookmarksExists = $Construct->columnExists("CountBookmarks");
$hotExists = $Construct->columnExists("hot");

$Construct
    ->primaryKey("DiscussionID")
    ->column("Type", "varchar(10)", true, "index")
    ->column("ForeignID", "varchar(32)", true, "index") // For relating foreign records to discussions
    ->column("CategoryID", "int", false, [
        "index.CategoryPages",
        "index.CategoryInserted",
        "index.Status_DateInserted",
        "index.Status_Hot",
        "index.Status_Score",
        "index.InternalStatus_DateInserted",
        "index.InternalStatus_Hot",
        "index.InternalStatus_Score",
        "index.Category_DateInserted",
        "index.Category_DateLastComment",
        "index.Category_Hot",
        "index.Category_Score",
    ])
    ->column("statusID", "int(11)", 0, ["index.Status_DateInserted", "index.Status_Hot", "index.Status_Score"])
    ->column("internalStatusID", "int(11)", RecordStatusModel::DISCUSSION_INTERNAL_STATUS_NONE, [
        "index.InternalStatus_DateInserted",
        "index.InternalStatus_Hot",
        "index.InternalStatus_Score",
    ])
    ->column("InsertUserID", "int", false, "key")
    ->column("UpdateUserID", "int", true)
    ->column("FirstCommentID", "int", true)
    ->column("LastCommentID", "int", true)
    ->column("Name", "varchar(100)", false, "fulltext")
    ->column("Body", "mediumtext", false, "fulltext")
    ->column("Format", "varchar(20)", true)
    ->column("Tags", "text", null)
    ->column("CountComments", "int", "0")
    ->column("CountBookmarks", "int", null)
    ->column("CountViews", "int", "1")
    ->column("Closed", "tinyint(1)", "0")
    ->column("Announce", "tinyint(1)", "0", "index")
    ->column("Sink", "tinyint(1)", "0")
    ->column("DateInserted", "datetime", false, [
        "index.CategoryInserted",
        "index.Status_DateInserted",
        "index.InternalStatus_DateInserted",
        "index.Category_DateInserted",
    ])
    ->column("DateUpdated", "datetime", true)
    ->column("InsertIPAddress", "ipaddress", true)
    ->column("UpdateIPAddress", "ipaddress", true)
    ->column("DateLastComment", "datetime", null, ["index.CategoryPages", "index.Category_DateLastComment"])
    ->column("LastCommentUserID", "int", true)
    ->column("Score", "float", null, [
        "index",
        "index.Status_Score",
        "index.InternalStatus_Score",
        "index.Category_Score",
    ])
    ->column("Attributes", "text", true)
    ->column("RegardingID", "int(11)", true, "index")
    ->column("hot", "bigint(20)", 0, ["index.Status_Hot", "index.InternalStatus_Hot", "index.Category_Hot"]);

$Construct->set($Explicit, $Drop);

// These indexes have been replaced with new ones for discussion API sorting and filtering
$Construct
    ->table("Discussion")
    ->dropIndexIfExists("IX_Discussion_QnA")
    ->dropIndexIfExists("IX_Discussion_DateInserted")
    ->dropIndexIfExists("IX_Discussion_DateLastComment")
    ->dropIndexIfExists("IX_Discussion_statusID")
    ->dropIndexIfExists("IX_Discussion_hot");

if ($DiscussionExists && !$FirstCommentIDExists) {
    $Px = $SQL->Database->DatabasePrefix;
    $UpdateSQL = "update {$Px}Discussion d set FirstCommentID = (select min(c.CommentID) from {$Px}Comment c where c.DiscussionID = d.DiscussionID)";
    $SQL->query($UpdateSQL, "update");
}
$indexStatusDateLastCommentExists = $SQL
    ->query(
        "SELECT 1 IndexExists FROM INFORMATION_SCHEMA.STATISTICS
WHERE table_schema=DATABASE() AND table_name='GDN_Discussion'
AND index_name='IX_Discussion_Status_DateLastComment'"
    )
    ->count();

if (!$indexStatusDateLastCommentExists) {
    $SQL->query("
        ALTER TABLE `GDN_Discussion`
        ADD INDEX IX_Discussion_Status_DateLastComment (`CategoryID`, `statusID`, `DateLastComment`),
        ADD INDEX IX_Discussion_InternalStatus_DateLastComment (`CategoryID`, `internalStatusID`, `DateLastComment`),
        ALGORITHM=INPLACE, LOCK=NONE
    ");
}

$Construct
    ->table("UserCategory")
    ->column("UserID", "int", false, "primary")
    ->column("CategoryID", "int", false, "primary")
    ->column("DateMarkedRead", "datetime", null)
    ->column("Followed", "tinyint(1)", 0);

// This column should be removed when muting categories is dropped in favor of category following..
$Construct->column("Unfollow", "tinyint(1)", 0);

$Construct->set($Explicit, $Drop);

// Allows the tracking of relationships between discussions and users (bookmarks, dismissed announcements, # of read comments in a discussion, etc)
// column($Name, $Type, $Length = '', $Null = FALSE, $Default = null, $KeyType = FALSE, $AutoIncrement = FALSE)
$Construct->table("UserDiscussion");

$ParticipatedExists = $Construct->columnExists("Participated");

$Construct
    ->column("UserID", "int", false, [
        "primary",
        "index.UserID_Bookmarked",
        "index.UserID_Participated",
        "index.DiscussionID_Bookmarked",
        "index.DiscussionID_Participated",
    ])
    ->column("DiscussionID", "int", false, ["primary", "key"])
    ->column("Score", "float", null)
    ->column("CountComments", "int", "0")
    ->column("DateLastViewed", "datetime", null) // null signals never
    ->column("Dismissed", "tinyint(1)", "0") // relates to dismissed announcements
    ->column("Bookmarked", "tinyint(1)", "0", ["index.UserID_Bookmarked", "index.DiscussionID_Bookmarked"])
    ->column("Participated", "tinyint(1)", "0", ["index.UserID_Participated", "index.DiscussionID_Participated"]) // whether or not the user has participated in the discussion.
    ->set($Explicit, $Drop);

$Construct->table("Comment");

if ($Construct->tableExists()) {
    $CommentIndexes = $Construct->indexSqlDb();
} else {
    $CommentIndexes = [];
}

$Construct
    ->table("Comment")
    ->primaryKey("CommentID")
    //->column('Type', 'varchar(10)', true)
    //->column('ForeignID', 'varchar(32)', TRUE, 'index') // For relating foreign records to discussions
    ->column("InsertUserID", "int", true, "index.InsertUserID_DiscussionID")
    ->column("DiscussionID", "int", false, ["index.1", "index.InsertUserID_DiscussionID"])
    ->column("UpdateUserID", "int", true)
    ->column("DeleteUserID", "int", true)
    ->column("Body", "mediumtext", false, "fulltext")
    ->column("Format", "varchar(20)", true)
    ->column("DateInserted", "datetime", null, ["index.1", "index"])
    ->column("DateDeleted", "datetime", true)
    ->column("DateUpdated", "datetime", true)
    ->column("InsertIPAddress", "ipaddress", true)
    ->column("UpdateIPAddress", "ipaddress", true)
    ->column("Flag", "tinyint", 0)
    ->column("Score", "float", null, ["index"])
    ->column("Attributes", "text", true)
    //->column('Source', 'varchar(20)', true)
    ->set($Explicit, $Drop);

if (isset($CommentIndexes["FK_Comment_DiscussionID"])) {
    $SQL->query("drop index FK_Comment_DiscussionID on {$Px}Comment");
}
if (isset($CommentIndexes["FK_Comment_DateInserted"])) {
    $SQL->query("drop index FK_Comment_DateInserted on {$Px}Comment");
}

// Update the participated flag.
if (!$ParticipatedExists) {
    $SQL->update("UserDiscussion ud")
        ->join("Discussion d", "ud.DiscussionID = d.DiscussionID and ud.UserID = d.InsertUserID")
        ->set("ud.Participated", 1)
        ->put();

    $SQL->update("UserDiscussion ud")
        ->join("Comment d", "ud.DiscussionID = d.DiscussionID and ud.UserID = d.InsertUserID")
        ->set("ud.Participated", 1)
        ->put();
}

// Allows the tracking of already-read comments & votes on a per-user basis.
$Construct
    ->table("UserComment")
    ->column("UserID", "int", false, "primary")
    ->column("CommentID", "int", false, "primary")
    ->column("Score", "float", null)
    ->column("DateLastViewed", "datetime", null) // null signals never
    ->set($Explicit, $Drop);

// Add extra columns to user table for tracking discussions & comments
$Construct
    ->table("User")
    ->column("CountDiscussions", "int", null)
    ->column("CountUnreadDiscussions", "int", null)
    ->column("CountComments", "int", null, ["index"])
    ->column("CountDrafts", "int", null)
    ->column("CountBookmarks", "int", null)
    ->set();

$Construct
    ->table("Draft")
    ->primaryKey("DraftID")
    ->column("DiscussionID", "int", true, "key")
    ->column("CategoryID", "int", true, "key")
    ->column("Type", "varchar(10)", false, "key")
    ->column("InsertUserID", "int", false, "key")
    ->column("UpdateUserID", "int")
    ->column("Name", "varchar(100)", true)
    ->column("Tags", "varchar(255)", null)
    ->column("Closed", "tinyint(1)", "0")
    ->column("Announce", "tinyint(1)", "0")
    ->column("Sink", "tinyint(1)", "0")
    ->column("Body", "mediumtext")
    ->column("Format", "varchar(20)", true)
    ->column("DateInserted", "datetime")
    ->column("DateUpdated", "datetime", true)
    ->set($Explicit, $Drop);

// Insert some activity types
///  %1 = ActivityName
///  %2 = ActivityName Possessive
///  %3 = RegardingName
///  %4 = RegardingName Possessive
///  %5 = Link to RegardingName's Wall
///  %6 = his/her
///  %7 = he/she
///  %8 = RouteCode & Route

// X added a discussion
if ($SQL->getWhere("ActivityType", ["Name" => "NewDiscussion"])->numRows() == 0) {
    $SQL->insert("ActivityType", [
        "AllowComments" => "0",
        "Name" => "NewDiscussion",
        "FullHeadline" => '%1$s started a %8$s.',
        "ProfileHeadline" => '%1$s started a %8$s.',
        "RouteCode" => "discussion",
        "Public" => "0",
    ]);
}

// X commented on a discussion.
$NewComment = $SQL->getWhere("ActivityType", ["Name" => "NewComment"])->firstRow();
$PluralNewComment = "<strong>{count}</strong> users commented on a discussion.";
if (!$NewComment) {
    $SQL->insert("ActivityType", [
        "AllowComments" => "0",
        "Name" => "NewComment",
        "FullHeadline" => '%1$s commented on a discussion.',
        "ProfileHeadline" => '%1$s commented on a discussion.',
        "PluralHeadline" => $PluralNewComment,
        "RouteCode" => "discussion",
        "Public" => "0",
    ]);
} else {
    $SQL->replace(
        "ActivityType",
        ["PluralHeadline" => $PluralNewComment],
        ["ActivityTypeID" => $NewComment->ActivityTypeID],
        true
    );
}

// People's comments on discussions
$DiscussionComment = $SQL->getWhere("ActivityType", ["Name" => "DiscussionComment"])->firstRow();
$PluralDiscussionComment =
    'There are <strong>{count}</strong> new comments on <a href="{Url,html}">{Data.Name,text}</a>.';
if (!$DiscussionComment) {
    $SQL->insert("ActivityType", [
        "AllowComments" => "0",
        "Name" => "DiscussionComment",
        "FullHeadline" => '%1$s commented on %4$s %8$s.',
        "ProfileHeadline" => '%1$s commented on %4$s %8$s.',
        "PluralHeadline" => $PluralDiscussionComment,
        "RouteCode" => "discussion",
        "Notify" => "1",
        "Public" => "0",
    ]);
} else {
    $SQL->replace(
        "ActivityType",
        ["PluralHeadline" => $PluralDiscussionComment],
        ["ActivityTypeID" => $DiscussionComment->ActivityTypeID],
        true
    );
}

// People mentioning others in discussion topics
if ($SQL->getWhere("ActivityType", ["Name" => "DiscussionMention"])->numRows() == 0) {
    $SQL->insert("ActivityType", [
        "AllowComments" => "0",
        "Name" => "DiscussionMention",
        "FullHeadline" => '%1$s mentioned %3$s in a %8$s.',
        "ProfileHeadline" => '%1$s mentioned %3$s in a %8$s.',
        "RouteCode" => "discussion",
        "Notify" => "1",
        "Public" => "0",
    ]);
}

// People mentioning others in comments
if ($SQL->getWhere("ActivityType", ["Name" => "CommentMention"])->numRows() == 0) {
    $SQL->insert("ActivityType", [
        "AllowComments" => "0",
        "Name" => "CommentMention",
        "FullHeadline" => '%1$s mentioned %3$s in a %8$s.',
        "ProfileHeadline" => '%1$s mentioned %3$s in a %8$s.',
        "RouteCode" => "comment",
        "Notify" => "1",
        "Public" => "0",
    ]);
}

// People commenting on user's bookmarked discussions
$BookmarkComment = $SQL->getWhere("ActivityType", ["Name" => "BookmarkComment"])->firstRow();
$PluralBookmarkComment =
    'There are <strong>{count}</strong> new comments on <a href="{Url,html}">{Data.Name,text}</a>.';
if (!$BookmarkComment) {
    $SQL->insert("ActivityType", [
        "AllowComments" => "0",
        "Name" => "BookmarkComment",
        "FullHeadline" => '%1$s commented on your %8$s.',
        "ProfileHeadline" => '%1$s commented on your %8$s.',
        "PluralHeadline" => $PluralBookmarkComment,
        "RouteCode" => "bookmarked discussion",
        "Notify" => "1",
        "Public" => "0",
    ]);
} else {
    $SQL->replace(
        "ActivityType",
        ["PluralHeadline" => $PluralBookmarkComment],
        ["ActivityTypeID" => $BookmarkComment->ActivityTypeID],
        true
    );
}

$ActivityModel = new ActivityModel();
$ActivityModel->defineType("Discussion");
$ActivityModel->defineType("Comment");
$SQL->replace("ActivityType", ["PluralHeadline" => $PluralDiscussionComment], ["Name" => "Comment"], true);

$PermissionModel = Gdn::permissionModel();
$PermissionModel->Database = Gdn::database();
$PermissionModel->SQL = $SQL;

// Define some global vanilla permissions.
$PermissionModel->define([
    "Vanilla.Approval.Require",
    "Vanilla.Comments.Me" => 1,
    "Vanilla.Discussions.CloseOwn" => 0,
    "Garden.NoAds.Allow" => 0,
]);
$PermissionModel->undefine(["Vanilla.Settings.Manage", "Vanilla.Categories.Manage"]);

// Define some permissions for the Vanilla categories.
$PermissionModel->define(
    [
        "Vanilla.Discussions.View" => 1,
        "Vanilla.Discussions.Add" => 1,
        "Vanilla.Discussions.Edit" => 0,
        "Vanilla.Discussions.Announce" => 0,
        "Vanilla.Discussions.Sink" => 0,
        "Vanilla.Discussions.Close" => 0,
        "Vanilla.Discussions.Delete" => 0,
        "Vanilla.Comments.Add" => 1,
        "Vanilla.Comments.Edit" => 0,
        "Vanilla.Comments.Delete" => 0,
    ],
    "tinyint",
    "Category",
    "PermissionCategoryID"
);

$PermissionModel->undefine("Vanilla.Spam.Manage");

/*
Apr 26th, 2010
Removed FirstComment from :_Discussion and moved it into the discussion table.
*/
$Prefix = $SQL->Database->DatabasePrefix;

if ($FirstCommentIDExists && !$BodyExists) {
    $SQL->query("update {$Prefix}Discussion, {$Prefix}Comment
   set {$Prefix}Discussion.Body = {$Prefix}Comment.Body,
      {$Prefix}Discussion.Format = {$Prefix}Comment.Format
   where {$Prefix}Discussion.FirstCommentID = {$Prefix}Comment.CommentID");

    $SQL->query("delete {$Prefix}Comment
   from {$Prefix}Comment inner join {$Prefix}Discussion
   where {$Prefix}Comment.CommentID = {$Prefix}Discussion.FirstCommentID");
}

if (!$LastCommentIDExists || !$LastCommentUserIDExists) {
    $SQL->query("update {$Prefix}Discussion d
   inner join {$Prefix}Comment c
      on c.DiscussionID = d.DiscussionID
   inner join (
      select max(c2.CommentID) as CommentID
      from {$Prefix}Comment c2
      group by c2.DiscussionID
   ) c2
   on c.CommentID = c2.CommentID
   set d.LastCommentID = c.CommentID,
      d.LastCommentUserID = c.InsertUserID
where d.LastCommentUserID is null");
}

if (!$CountBookmarksExists) {
    $SQL->query("update {$Prefix}Discussion d
   set CountBookmarks = (
      select count(ud.DiscussionID)
      from {$Prefix}UserDiscussion ud
      where ud.Bookmarked = 1
         and ud.DiscussionID = d.DiscussionID
   )");
}

$Construct->table("TagDiscussion");
$DateInsertedExists = $Construct->columnExists("DateInserted");

$Construct
    ->column("TagID", "int", false, "primary")
    ->column("DiscussionID", "int", false, ["primary", "index.DiscussionID"])
    ->column("CategoryID", "int", false, "index")
    ->column("DateInserted", "datetime", !$DateInsertedExists)
    ->engine("InnoDB")
    ->set($Explicit, $Drop);

if (!$DateInsertedExists) {
    $SQL->update("TagDiscussion td")
        ->join("Discussion d", "td.DiscussionID = d.DiscussionID")
        ->set("td.DateInserted", "d.DateInserted", false, false)
        ->put();
}

$Construct
    ->table("Tag")
    ->column("CountDiscussions", "int", 0)
    ->set();

//Structure to hold collections
if ($Construct->tableExists("contentGroup")) {
    //Rename existing table/column structure
    $contentGroupTable = $SQL->prefixTable("contentGroup");
    $collectionTable = $SQL->prefixTable("collection");
    if ($Construct->table("contentGroup")->columnExists("contentGroupID")) {
        $SQL->query(
            "alter table {$contentGroupTable} CHANGE column contentGroupID collectionID int not null auto_increment"
        );
    }
    $Construct->renameTable($contentGroupTable, $collectionTable);
} else {
    //create new collection structure
    $Construct
        ->table("collection")
        ->primaryKey("collectionID")
        ->column("name", "varchar(255)", true)
        ->column("insertUserID", "int", false, "key")
        ->column("updateUserID", "int", true)
        ->column("dateInserted", "datetime")
        ->column("dateUpdated", "datetime", true)
        ->set($Explicit, $Drop);
}
if ($Construct->tableExists("contentGroupRecord")) {
    $contentGroupRecordTable = $SQL->prefixTable("contentGroupRecord");
    $collectionRecordTable = $SQL->prefixTable("collectionRecord");
    if ($Construct->table("contentGroupRecord")->columnExists("contentGroupID")) {
        $SQL->query("alter table {$contentGroupRecordTable} CHANGE column contentGroupID collectionID int not null");
    }
    $Construct->renameTable($contentGroupRecordTable, $collectionRecordTable);
} else {
    $Construct
        ->table("collectionRecord")
        ->column("collectionID", "int", false, "primary")
        ->column("recordID", "int", false, ["primary", "index.recordID"])
        ->column("recordType", "varchar(50)", false, ["primary", "index.recordType"])
        ->column("sort", "int", 30)
        ->set($Explicit, $Drop);
}
$Categories = Gdn::sql()
    ->where("coalesce(UrlCode, '') =", "''", false, false)
    ->get("Category")
    ->resultArray();
foreach ($Categories as $Category) {
    $UrlCode = Gdn_Format::url($Category["Name"]);
    if (strlen($UrlCode) > 50) {
        $UrlCode = $Category["CategoryID"];
    }

    Gdn::sql()->put("Category", ["UrlCode" => $UrlCode], ["CategoryID" => $Category["CategoryID"]]);
}

// Moved this down here because it needs to run after GDN_Comment is created
if (!$LastDiscussionIDExists) {
    $SQL->update("Category c")
        ->join("Comment cm", "c.LastCommentID = cm.CommentID")
        ->set("c.LastDiscussionID", "cm.DiscussionID", false, false)
        ->put();
}

if (!$captureOnly) {
    if (!$CountAllDiscussionsExists) {
        CategoryModel::instance()->counts("CountAllDiscussions");
    }
    if (!$CountAllCommentsExists) {
        CategoryModel::instance()->counts("CountAllComments");
    }
}

// Override MaxLength settings that are too high for the database
$maxCommentLength = Gdn::config("Vanilla.Comment.MaxLength");
if ($maxCommentLength > DiscussionModel::MAX_POST_LENGTH) {
    saveToConfig("Vanilla.Comment.MaxLength", DiscussionModel::MAX_POST_LENGTH);
}

$Construct
    ->table("dirtyRecord")
    ->column("recordType", "varchar(50)", false, ["primary", "index.recordType"])
    ->column("recordID", "int", false, ["primary"])
    ->column("dateInserted", "datetime", false, ["index.recordType"])
    ->set();

// Add stub content
include PATH_APPLICATIONS . DS . "vanilla" . DS . "settings" . DS . "stub.php";

$defaultEmails = [
    "system@example.com",
    "vanilla@stub.vanillacommunity.com",
    "karen@stub.vanillacommunity.com",
    "victorine@stub.vanillacommunity.com",
    "alex@stub.vanillacommunity.com",
];

$users = [];
foreach ($defaultEmails as $email) {
    $user = Gdn::userModel()
        ->getWhere(["Email" => $email])
        ->firstRow(DATASET_TYPE_ARRAY);

    if ($user) {
        $users[] = $user;
    }
}

foreach ($users as $user) {
    if ($user) {
        $emailPrefix = explode("@", $user["Email"]);
        $SQL->update("User")
            ->set("Email", $emailPrefix[0] . "@vanillacommunity.example")
            ->where("email", $user["Email"])
            ->put();
    }
}

if (!$hotExists) {
    $SQL->update("Discussion")
        ->set("hot", "0 + COALESCE(Score, 0) + COALESCE(CountComments, 0)", false)
        ->put();
}

// Convert old 16 char default salt to 32 char.
$cookieSalt = $config->get("Garden.Cookie.Salt");
if (strlen($cookieSalt) === 16 && !$config->configKeyExists("Garden.Cookie.OldSalt")) {
    // Assume if salt length is 16 then we are using the old default salt.
    $config->set("Garden.Cookie.OldSalt", $cookieSalt);
    $config->set("Garden.Cookie.Salt", betterRandomString(32, "Aa0"));
}
