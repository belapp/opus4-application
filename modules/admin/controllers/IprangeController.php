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
 * @package     Module_Iprange
 * @author      Felix Ostrowski <ostrowski@hbz-nrw.de>
 * @author      Pascal-Nicolas Becker <becker@zib.de>
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

/**
 * Controller for management of IP ranges.
 *
 * @category    Application
 * @package     Module_Iprange
 */
class Admin_IprangeController extends Controller_Action {

    /**
     * Show table with all defined IP ranges and process requests.
     */
    public function indexAction() {
        if ($this->getRequest()->isPost()) {
            $request = $this->getRequest();
            $buttonEdit = $request->getPost('actionEdit');
            $buttonDelete = $request->getPost('actionDelete');

            if (isset($buttonEdit)) {
                $this->_forwardToAction('edit');
            }
            else if (isset($buttonDelete)) {
                $this->_forwardToAction('delete');
            }
        }

        $this->view->title = $this->view->translate('admin_iprange_index');

        $ipRanges = Opus_Iprange::getAll();

        if (empty($ipRanges)) {
            return $this->renderScript('iprange/none.phtml');
        }
        else {
            $this->view->ipRanges = array();
            foreach ($ipRanges as $ipRange) {
                $this->view->ipRanges[$ipRange->getId()] = $ipRange;
            }
        }
    }

    /**
     * Show IP range information.
     */
    public function showAction() {
        $this->view->title = $this->view->translate('admin_iprange_action_show');

        $id = $this->getRequest()->getParam('id');

        if (!empty($id)) {
            $ipRange = new Opus_Iprange($id);
            $this->view->ipRange = $ipRange;
        }
        else {
            $this->_helper->redirector('index');
        }
    }
    
    public function editAction() {
        
    }
    
    public function newAction() {
        $this->view->title = $this->view->translate('admin_iprange_index');

        $form = new Admin_Form_IpRange();
        
        $actionUrl = $this->view->url(array('action' => 'create'));

        $form->setAction($actionUrl);

        $this->view->form = $form;


    }
    
    public function createAction() {
        $form = new Admin_Form_IpRange();

        if ($this->getRequest()->isPost()) {
            $postData = $this->getRequest()->getPost();

            $button = $postData['cancel'];
            if (isset($button)) {
                $this->_helper->redirector('index');
            }

            if ($form->isValid($postData)) {
                $name = $postData['name'];
                $startingIp = $postData['startingip'];
                $endingIp = $postData['endingip'];

                if (empty($endingIp)) {
                    // single address IP range
                    $endingIp = $startingIp;
                }

                $roles = $form->getSelectedRoles($postData);

                $ipRange = new Opus_Iprange();

                $ipRange->setName($name);
                $ipRange->setStartingip($startingIp);
                $ipRange->setEndingip($endingIp);
                $ipRange->setRole($roles);
                
                $ipRange->store();
            }
            else {
                $actionUrl = $this->view->url(array('action' => 'create'));
                $form->setAction($actionUrl);
                $this->view->form = $form;
                return $this->renderScript('iprange/new.phtml');
            }
        }

        $this->_helper->redirector('index');
    }
    
    public function updateAction() {
        
    }

    public function deleteAction() {
        $id = $this->getRequest()->getParam('id');

        $ipRange = new Opus_Iprange($id);

        $ipRange->delete();

        $this->_helper->redirector('index');
    }

}
