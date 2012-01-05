<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    Application
 * @package     Module_Admin
 * @author      Gunar Maiwald <maiwald@zib.de>
 * @copyright   Copyright (c) 2008-2011, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id: Enrichmentkey.php 9260 2011-12-20 10:44:39Z gmaiwald $
 */

/**
 * Form for creating and editing a role.
 */
class Admin_Form_Enrichmentkey extends Zend_Form {

    //private static $protectedRoles = array('administrator', 'guest');

    /**
     * Constructs form.
     * @param int $id
     */
    public function __construct($name = null) {
        $section = empty($name) ? 'new' : 'edit';

        $config = new Zend_Config_Ini(APPLICATION_PATH .
                '/modules/admin/forms/enrichmentkey.ini', $section);

        parent::__construct($config->form->enrichmentkey);

        if (!empty($name)) {
            $enrichmentkey = new Opus_Enrichmentkey($name);
            $this->populateFromEnrichmentkey($enrichmentkey);
        }
    }

    public function init() {
        parent::init();
        $this->getElement('name')->addValidator(new Form_Validate_EnrichmentkeyAvailable());
    }

    public function populateFromEnrichmentkey($enrichmentkey) {
        $nameElement = $this->getElement('name');
        $name = $enrichmentkey->getName();
        $nameElement->setValue($enrichmentkey);
    }

}