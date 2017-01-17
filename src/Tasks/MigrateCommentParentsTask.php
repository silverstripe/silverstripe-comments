<?php

namespace SilverStripe\Comments\Tasks;

use SilverStripe\Comments\Model\Comment;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Migrates all 3.x comment's BaseClass fields to the new ParentClass fields
 *
 * @package comments
 */
class MigrateCommentParentsTask extends BuildTask
{
    private static $segment = 'MigrateCommentParentsTask';

    protected $title = 'Migrate Comment Parent classes from 3.x';

    protected $description = 'Migrates all 3.x Comment BaseClass fields to the new ParentClass fields in 4.0';

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        // Check if anything needs to be done
        $tableName = Comment::getSchema()->tableName(Comment::class);
        if (!DB::get_schema()->hasField($tableName, 'BaseClass')) {
            DB::alteration_message('"BaseClass" does not exist on "' . $tableName . '", nothing to upgrade.', 'notice');
            return;
        }

        // Set the class names to fully qualified class names first
        $remapping = Config::inst()->get('SilverStripe\\ORM\\DatabaseAdmin', 'classname_value_remapping');
        $updateQuery = "UPDATE \"Comment\" SET \"BaseClass\" = ? WHERE \"BaseClass\" = ?";
        foreach ($remapping as $old => $new) {
            DB::prepared_query($updateQuery, [$new, $old]);
        }

        // Move these values to ParentClass (the 4.x column name)
        DB::query('UPDATE "Comment" SET "ParentClass" = "BaseClass"');
        DB::alteration_message('Finished updating any applicable Comment class columns', 'notice');
    }
}
