<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Comments\Admin\CommentAdmin;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;

class CommentAdminTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testProvidePermissions()
    {
        $commentAdmin = new CommentAdmin();
        $locale = i18n::get_locale();

        i18n::set_locale('fr');
        $expected = array(
            'CMS_ACCESS_CommentAdmin' => array(
                'name' => 'Accès à la section Commentaires',
                'category' => 'Accès au CMS',
            )
        );

        $this->assertEquals($expected, $commentAdmin->providePermissions());

        i18n::set_locale($locale);
        $expected = array(
            'CMS_ACCESS_CommentAdmin' => array(
                'name' => 'Access to \'Comments\' section',
                'category' => 'CMS Access'
            )
        );
        $this->assertEquals($expected, $commentAdmin->providePermissions());
    }
}
