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
 * @package     Application - Module Publish
 * @author      Susanne Gottwald <gottwald@zib.de>
 * @copyright   Copyright (c) 2008-2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Publish_2_IndexController$
 */

/**
 * Main entry point for this module.
 *
 * @category    Application
 * @package     Module_Publish
 */
class Publish_FormController extends Controller_Action {
    CONST FIRST = "Firstname";
    CONST COUNTER = "1";
    CONST GROUP = "group";
    CONST EXPERT = "X";
    CONST LABEL = "_label";
    CONST ERROR = "Error";

    public $log;
    public $session;

    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array()) {
        $this->log = Zend_Registry::get('Zend_Log');
        $this->session = new Zend_Session_Namespace('Publish');

        parent::__construct($request, $response, $invokeArgs);
    }

    public function uploadAction() {
        $this->view->languageSelectorDisabled = true;
        $this->view->title = $this->view->translate('publish_controller_index');

        if ($this->getRequest()->isPost() === true) {

            //initializing
            $indexForm = new Publish_Form_PublishingFirst($this->view);
            $data = $this->getRequest()->getPost();
            $indexForm->populate($data);
            $this->_initializeDocument($data);

            //reject manipulated hidden field for file size
            if (isset($data['MAX_FILE_SIZE']) && $data['MAX_FILE_SIZE'] != $this->session->maxFileSize) {
                $this->log->debug("wrong Max_file_size and redirect to index");
                return $this->_redirectTo('index', '', 'index');
            }

            //validate fileupload
            if (!$indexForm->getElement('fileupload')->isValid($data)) {
                $this->view->form = $indexForm;
                $this->view->subtitle = $this->view->translate('publish_controller_index_sub');
                $this->view->requiredHint = $this->view->translate('publish_controller_required_hint');
                $this->view->errorCaseMessage = $this->view->translate('publish_controller_form_errorcase');
                $this->_setFirstFormViewVariables($indexForm);
            }
            else {
                //file valid-> store file
                $this->view->subtitle = $this->view->translate('publish_controller_index_anotherFile');
                $this->view->form = $indexForm;
                $this->_setFirstFormViewVariables($indexForm);
                $this->session->uploadSuccess = $this->_storeUploadedFiles();

                if (array_key_exists('addAnotherFile', $data))
                    return $this->renderScript('index/index.phtml');
            }

            //validate whole form
            if (!$indexForm->isValid($data)) {
                $this->view->form = $indexForm;
                $this->view->subtitle = $this->view->translate('publish_controller_index_sub');
                $this->view->requiredHint = $this->view->translate('publish_controller_required_hint');
                $this->view->errorCaseMessage = $this->view->translate('publish_controller_form_errorcase');
                $this->_setFirstFormViewVariables($indexForm);
                return $this->renderScript('index/index.phtml');
            }

            //form entries are valid: store data
            $this->_storeBibliography($data);
            $this->_storeSubmitterEnrichment();

            //call the appropriate template
            return $this->_showTemplate();
        }
        return $this->_redirectTo('index', '', 'index');
    }

    /**
     * displays and checks the publishing form contents and calls deposit to store the data
     * uses check_array
     * @return <type>
     */
    public function checkAction() {
        $this->view->languageSelectorDisabled = true;
        $reload = true;

        if ($this->getRequest()->isPost() === true) {
            $postData = $this->getRequest()->getPost();

            if (array_key_exists('abort', $postData))
                return $this->_redirectTo('index', '', 'index');

            if (array_key_exists('back', $postData) || array_key_exists('abortCollection', $postData)) {
                $reload = false;
                if (isset($this->session->elements))
                    foreach ($this->session->elements AS $element)
                        $postData[$element['name']] = htmlspecialchars($element['value']);
            }

            //initialize the form object
            $form = new Publish_Form_PublishingSecond($this->session->documentType, $this->session->documentId, $this->session->fulltext, $this->session->additionalFields, $postData);

            if (array_key_exists('abortCollection', $postData)) {
                $form = $form->populate($postData);
                return $this->_showCheckPage($form);
            }

            if (!$form->send->isChecked() || array_key_exists('back', $postData)) {
                // A button (not SEND) was pressed => add / remove fields

                $this->view->title = $this->view->translate('publish_controller_index');
                $this->view->subtitle = $this->view->translate($this->session->documentType);
                $this->view->requiredHint = $this->view->translate('publish_controller_required_hint');

                $this->_helper->viewRenderer($this->session->documentType);

                //call method to add or delete buttons
                return $this->_getExtendedForm($form, $postData, $reload);
            }

            // SEND was pressed => check the form
            $this->view->title = $this->view->translate('publish_controller_index');
            $this->view->subtitle = $this->view->translate($this->session->documentType);
            $this->view->requiredHint = $this->view->translate('publish_controller_required_hint');

            if (!$form->isValid($this->getRequest()->getPost())) {
                //Variables are invalid
                $this->_setSecondFormViewVariables($form);
                $this->view->form = $form;
                $this->view->errorCaseMessage = $this->view->translate('publish_controller_form_errorcase');
                //error case, and redirect to form, show errors
                return $this->render($this->session->documentType);
            }

            return $this->_showCheckPage($form);
        }

        return $this->_redirectTo('upload');
    }

    private function _showTemplate() {
        $templateName = $this->_helper->documentTypes->getTemplateName($this->session->documentType);
        $this->_helper->viewRenderer($templateName);
        $this->view->subtitle = $this->view->translate($this->session->documentType);
        $this->view->requiredHint = $this->view->translate('publish_controller_required_hint');
        $this->view->doctype = $this->session->documentType;

        $publishForm = new Publish_Form_PublishingSecond($this->session->documentType, $this->session->documentId, $this->session->fulltext, $this->session->additionalFields, null);
        $action_url = $this->view->url(array('controller' => 'form', 'action' => 'check')) . '#current';
        $publishForm->setAction($action_url);
        $publishForm->setMethod('post');
        $this->_setSecondFormViewVariables($publishForm);
        $this->view->action_url = $action_url;
        $this->view->form = $publishForm;
    }

    private function _showCheckPage($form) {

        // Form variables all VALID
        $this->log->debug("Variables are valid!");

        $this->view->title = $this->view->translate('publish_controller_index');
        $this->view->subtitle = $this->view->translate('publish_controller_check2');
        $this->view->header = $this->view->translate('publish_controller_changes');

        $depositForm = new Publish_Form_PublishingSecond($this->session->documentType, $this->session->documentId, $this->session->fulltext, $this->session->additionalFields, $form->getValues());
        $action_url = $this->view->url(array('controller' => 'deposit', 'action' => 'deposit'));
        $depositForm->setAction($action_url);
        $depositForm->setMethod('post');
        $depositForm->populate($form->getValues());
        $depositForm->prepareCheck();
        $this->view->action_url = $action_url;
        $this->view->form = $depositForm;
    }

    private function _setFirstFormViewVariables($form) {
        $errors = $form->getMessages();

        //first form single fields for view placeholders
        foreach ($form->getElements() AS $currentElement => $value) {
            //single field name (for calling with helper class)
            $elementAttributes = $form->getElementAttributes($currentElement); //array
            $this->view->$currentElement = $elementAttributes;
        }

        //Upload-Field
        $displayGroup = $form->getDisplayGroup('documentUpload');
        $this->session->numdocumentUpload = 2;
        $groupName = $displayGroup->getName();
        $groupFields = array(); //Fields
        $groupHiddens = array(); //Hidden fields for adding and deleting fields
        $groupButtons = array(); //Buttons

        foreach ($displayGroup->getElements() AS $groupElement) {

            $elementAttributes = $form->getElementAttributes($groupElement->getName()); //array
            if ($groupElement->getType() === 'Zend_Form_Element_Submit') {
                //buttons
                $groupButtons[$elementAttributes["id"]] = $elementAttributes;
            }
            else if ($groupElement->getType() === 'Zend_Form_Element_Hidden') {
                //hidden fields
                $groupHiddens[$elementAttributes["id"]] = $elementAttributes;
            }
            else {
                //normal fields
                $groupFields[$elementAttributes["id"]] = $elementAttributes;
            }
        }
        $group = array();
        $group["Fields"] = $groupFields;
        $group["Hiddens"] = $groupHiddens;
        $group["Buttons"] = $groupButtons;
        $group["Name"] = $groupName;
        $this->view->$groupName = $group;
        $this->view->MAX_FILE_SIZE = $this->session->maxFileSize;
    }

    /**
     * method to set the different variables and arrays for the view and the templates
     * @param <Zend_Form> $form
     */
    private function _setSecondFormViewVariables($form) {
        $this->session->elementCount = 0;
        $errors = $form->getMessages();

        //group fields and single fields for view placeholders
        foreach ($form->getElements() AS $currentElement => $value) {
            //element names have to loose special strings for finding groups
            $name = $this->_getRawElementName($currentElement);

            if (strstr($name, 'Enrichment')) {
                $name = str_replace('Enrichment', '', $name);
            }

            //build group name
            $groupName = self::GROUP . $name;
            $this->view->$name = $this->view->translate($name);

            //get the display group for the current element and build the complete group
            $displayGroup = $form->getDisplayGroup($groupName);
            if (!is_null($displayGroup)) {
                $group = $this->_buildViewDisplayGroup($displayGroup, $form);
                $group["Name"] = $groupName;
                $this->view->$groupName = $group;
                $this->viewElementsCount++;
            }

            //single field name (for calling with helper class)
            $elementAttributes = $form->getElementAttributes($currentElement); //array

            if (strstr($currentElement, 'Enrichment')) {
                $name = str_replace('Enrichment', '', $currentElement);
                $this->view->$name = $elementAttributes;
                $this->viewElementsCount++;
            }
            else {
                $this->view->$currentElement = $elementAttributes;
                $this->viewElementsCount++;
            }

            $label = $currentElement . self::LABEL;
            $this->view->$label = $this->view->translate($form->getElement($currentElement)->getLabel());

            //EXPERT VIEW:
            //also support more difficult templates for "expert admins"
            $expertField = $currentElement . self::EXPERT;
            $this->view->$expertField = $form->getElement($currentElement)->getValue();
            //error values for expert fields view
            if (isset($errors[$currentElement])) {
                foreach ($errors[$currentElement] as $error => $errorMessage) {
                    $errorElement = $expertField . self::ERROR;
                    $this->view->$errorElement = $errorMessage;
                }
            }
        }
    }

    /**
     * Method to find out the element name stemming.
     * @param <String> $element element name
     * @return <String> $name
     */
    private function _getRawElementName($element) {
        $name = "";
        //element is a person element
        $pos = stripos($element, self::FIRST);
        if ($pos !== false) {
            $name = substr($element, 0, $pos);
        }
        else {
            //element belongs to a group
            $pos = stripos($element, self::COUNTER);
            if ($pos != false) {
                $name = substr($element, 0, $pos);
            }
            else {
                //"normal" element name without changes
                $name = $element;
            }
        }
        return $name;
    }

    /**
     * Method to build a disply group by a number of arrays for fields, hidden fields and buttons.
     * @param <Zend_Form_DisplayGroup> $displayGroup
     * @param <Publishing_Second> $form
     * @return <Array> $group
     */
    private function _buildViewDisplayGroup($displayGroup, $form) {
        $groupFields = array(); //Fields
        $groupHiddens = array(); //Hidden fields for adding and deleting fields
        $groupButtons = array(); //Buttons

        foreach ($displayGroup->getElements() AS $groupElement) {

            $elementAttributes = $form->getElementAttributes($groupElement->getName()); //array
            if ($groupElement->getType() === 'Zend_Form_Element_Submit') {
                //buttons
                $groupButtons[$elementAttributes["id"]] = $elementAttributes;
            }
            else if ($groupElement->getType() === 'Zend_Form_Element_Hidden') {
                //hidden fields
                $groupHiddens[$elementAttributes["id"]] = $elementAttributes;
            }
            else {
                //normal fields
                $groupFields[$elementAttributes["id"]] = $elementAttributes;
            }
        }
        $group[] = array();
        $group["Fields"] = $groupFields;
        $group["Hiddens"] = $groupHiddens;
        $group["Buttons"] = $groupButtons;

        return $group;
    }

    /**
     * Method to check which button in the form was pressed.
     * @param <Zend_Form> $form
     * @return <String> name of button
     */
    private function _getPressedButton($form) {
        $this->log->debug("Method getPressedButton begins...");
        $pressedButton = "";
        foreach ($form->getElements() AS $element) {
            if ($element->getType() === 'Zend_Form_Element_Submit' && $element->isChecked()) {
                $this->log->debug('Following Button Is Checked: ' . $element->getName());
                $pressedButton = $element;
                $pressedButtonName = $pressedButton->getName();
                break;
            }
        }

        if ($pressedButton == "")
            throw new Publish_Model_OpusServerException("No pressed button found! Possibly the values of the buttons are not equal in the view and Publish class.");
        else
            return $pressedButtonName;
    }

    /**
     * Method stores th uploaded files
     */
    private function _initializeDocument($postData = null) {       
        if (!isset($this->session->documentId) || $this->session->documentId == '') {
            $this->session->document = new Opus_Document();
            $this->session->document->setServerState('temporary');
            $this->session->documentId = $this->session->document->store();
            $this->log->info(__METHOD__ . ' The corresponding document ID is: ' . $this->session->documentId);
        }

        if (isset($postData['documentType'])) {
            if ($postData['documentType'] !== '') {
                $this->session->documentType = $postData['documentType'];
                $this->log->info(__METHOD__ .  ' documentType = ' . $this->session->documentType);                
                $this->session->document->setType($this->session->documentType);
                $this->session->document->store();
            }
            unset($postData['documentType']);
        }

        $this->session->additionalFields = array();
    }

    /**
     * Method stores th uploaded files
     */
    private function _storeSubmitterEnrichment() {
        $loggedUserModel = new Publish_Model_LoggedUser();
        $userId = trim($loggedUserModel->getUserId());

        if (empty($userId)) {
            $this->log->debug("No user logged in.  Skipping enrichment.");
            return;
        }

        $this->session->document->addEnrichment()
                ->setKeyName('submitter.user_id')
                ->setValue($userId);
        $this->session->document->store();
    }

    /**
     * Method stores the uploaded files
     */
    private function _storeUploadedFiles() {
        $upload = new Zend_File_Transfer_Adapter_Http();
        $files = $upload->getFileInfo();
        $upload_count = 0;

        $uploaded_files = $this->session->document->getFile();
        $uploaded_files_names = array();
        foreach ($uploaded_files as $upfile) {
            $uploaded_files_names[$upfile->getPathName()] = $upfile->getPathName();
        }

        foreach ($files as $file) {
            if (!empty($file['name'])) {

                //file have already been uploaded
                if (array_key_exists($file['name'], $uploaded_files_names)) {
                        return false;
                }
                $upload_count++;
            }
        }

        $this->log->info("Fileupload of: " . count($files) . " potential files (vs. $upload_count really uploaded)");

        if ($upload_count < 1) {
            $this->log->debug("NO File uploaded!!!");
            $this->session->fulltext = '0';
            return;
        }

        $this->log->debug("File uploaded!!!");
        $this->session->fulltext = '1';

        foreach ($files AS $file => $fileValues) {
            if (!empty($fileValues['name'])) {
                $this->session->publishFiles[] = $fileValues['name'];
                $this->log->info("uploaded: " . $fileValues['name']);
                $docfile = $this->session->document->addFile();
                //file always requires a language, this value is later overwritten by the exact language
                $docfile->setLanguage("eng");
                $docfile->setFromPost($fileValues);
            }
        }
        $this->session->document->store();
        return true;
    }

    /**
     * Method stores the uploaded files
     */
    private function _storeBibliography($data) {
        if (isset($data['bibliographie']) && $data['bibliographie'] === '1') {
            $this->log->debug("Bibliographie is set -> store it!");
            //store the document internal field BelongsToBibliography
            $this->session->document->setBelongsToBibliography(1);
            $this->session->document->store();
        }
    }

    /**
     * Methodgets the current form and finds out which fields has to be edded or deleted
     * @param Publish_Form_PublishingSecond $form
     * @return <View>
     */
    private function _getExtendedForm($form, $postData=null, $reload=true) {
        $this->session->currentAnchor = "";
        if ($reload === true) {
            //find out which button was pressed
            $pressedButtonName = $this->_getPressedButton($form);

            if (substr($pressedButtonName, 0, 7) == "addMore") {
                $fieldName = substr($pressedButtonName, 7);
                $workflow = "add";
            }
            else if (substr($pressedButtonName, 0, 10) == "deleteMore") {
                $fieldName = substr($pressedButtonName, 10);
                $workflow = "delete";
            }

            $saveName = "";
            //Enrichment-Fruppen haben Enrichment im Namen, die aber mit den currentAnchor kollidieren
            $currentNumber = $this->session->additionalFields[$fieldName];
            if (strstr($fieldName, 'Enrichment')) {
                $saveName = $fieldName;
                $fieldName = str_replace('Enrichment', '', $fieldName);
            }

            $this->session->currentAnchor = 'group' . $fieldName;
            //erst Enrichment entfernen und dann unverändert weiter geben
            //todo: schönere Lösung als diese blöden String-Sachen!!!
            if ($saveName != "")
                $fieldName = $saveName;

            if ($workflow == "add") {
                //show one more fields
                $currentNumber = (int) $currentNumber + 1;
            }
            else {
                if ($currentNumber > 1) {
                    //remove one more field, only down to 0
                    $currentNumber = (int) $currentNumber - 1;
                }
            }
            //set the increased value for the pressed button and create a new form
            $this->session->additionalFields[$fieldName] = $currentNumber;
        }

        $form = new Publish_Form_PublishingSecond($this->session->documentType, $this->session->documentId, $this->session->fulltext, $this->session->additionalFields, $postData);
        $action_url = $this->view->url(array('controller' => 'form', 'action' => 'check')) . '#current';
        $form->setAction($action_url);
        $this->view->action_url = $action_url;
        $this->_setSecondFormViewVariables($form);
        $this->view->form = $form;

        return $this->render($this->session->documentType);
    }

}