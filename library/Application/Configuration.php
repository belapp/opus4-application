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
 * @package     Application
 * @author      Jens Schwidder <schwidder@zib.de>
 * @author      Michael Lang   <lang@zib.de>
 * @copyright   Copyright (c) 2008-2014, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

/**
 * Klasse für das Laden von Übersetzungsressourcen.
 */
class Application_Configuration {

    /**
     * Defaultsprache.
     */
    const DEFAULT_LANGUAGE = 'en';

    /**
     * Logger.
     * @var Zend_Log
     */
    private $_logger = null;
    
    /**
     * Unterstützte Sprachen.
     * @var array
     */
    private $_supportedLanguages = null;

    /**
     * Is language selection active in user interface.
     */
    private $_languageSelectionEnabled = null;

    /**
     * Liefert den Logger für diese Klasse.
     * @return Zend_Log
     */
    public function getLogger() {
        if (is_null($this->_logger)) {
            $this->_logger = Zend_Registry::get('Zend_Log');
        }

        return $this->_logger;
    }

    /**
     * Setzt den Logger für diese Klasse.
     */
    public function setLogger($logger) {
        $this->_logger = $logger;
    }
    
    /**
     * Liefert die Konfiguration für Applikation.
     * @return Zend_Config
     */
    public function getConfig() {
        return Zend_Registry::get('Zend_Config');
    }
    
    /**
     * Liefert die Sprachen, die von OPUS unterstützt werden.
     * @return array
     */
    public function getSupportedLanguages() {
        if (is_null($this->_supportedLanguages)) {
            $config = $this->getConfig();
            if (isset($config->supportedLanguages)) {
                $this->_supportedLanguages = explode(",", $config->supportedLanguages);
                $this->getLogger()->debug(
                    Zend_Debug::dump(
                        $this->_supportedLanguages, 'Supported languages ('
                        . count($this->_supportedLanguages) . ')', false
                    )
                );
            }
        }
        return $this->_supportedLanguages;
    }
    
    /**
     * Prüft, ob eine Sprache unterstützt wird.
     * @param string $language Sprachcode (z.B. 'en')
     * @return bool
     */
    public function isLanguageSupported($language) {
        $languages = $this->getSupportedLanguages();
        return in_array($language, $languages);
    }

    /**
     * Liefert Defaultsprache für Userinterface.
     * @return string
     */
    public function getDefaultLanguage() {
        if ($this->isLanguageSelectionEnabled()) {
            return self::DEFAULT_LANGUAGE;
        }
        else {
            $languages = $this->getSupportedLanguages();
            return $languages[0];
        }
    }

    /**
     * Prüft, ob mehr als eine Sprache unterstützt wird.
     * @return bool
     */
    public function isLanguageSelectionEnabled() {
        if (is_null($this->_languageSelectionEnabled)) {
            $this->_languageSelectionEnabled = count($this->getSupportedLanguages()) > 1;
        }
        return $this->_languageSelectionEnabled;
    }

    /**
     * Liest Inhalt von VERSION.txt um die installierte Opusversion zu ermitteln.
     */
    public static function getOpusVersion() {
        $config = Zend_Registry::get('Zend_Config');
        $localVersion = $config->version;
        return (is_null($localVersion)) ? 'unknown' : $version = $localVersion;
    }

    /**
     * Liefert Informationen als Key -> Value Paare in einem Array.
     */
    public static function getOpusInfo() {
        $info = array();
        $info['admin_info_version'] = self::getOpusVersion();
        return $info;
    }
        
}
