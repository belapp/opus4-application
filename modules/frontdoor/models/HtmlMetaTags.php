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
 * @package     Module_Frontdoor
 * @author      Sascha Szott <opus-development@saschaszott.de>
 * @copyright   Copyright (c) 2008-2019, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */
class Frontdoor_Model_HtmlMetaTags
{
    /**
     * @var Zend_Config
     */
    private $config;

    /**
     * @var string
     */
    private $fullUrl;

    /**
     * Frontdoor_Model_HtmlMetaTags constructor.
     *
     * @param Zend_Config $config
     * @param string $fullUrl
     */
    public function __construct($config, $fullUrl)
    {
        $this->config = $config;
        $this->fullUrl = $fullUrl;
    }

    /**
     * @param Opus_Document $document
     * @return array Array mit Metatag-Paaren
     */
    public function createTags($document) {
        $metas = [];
        $this->handleAuthors($document, $metas);
        $this->handleDates($document, $metas);
        $this->handleTitles($document, $metas);
        $this->handleSimpleAttribute($document->getPublisherName(), ['DC.publisher', 'citation_publisher'], $metas);

        if ($this->isJournalPaper($document)) {
            $this->handleJournalTitle($document, $metas);
        }

        if ($this->isJournalPaper($document) || $this->isConferencePaper($document) || $this->isWorkingPaper($document)) {
            $this->handleSimpleAttribute($document->getVolume(), ['DC.citation.volume', 'citation_volume'], $metas);
            $this->handleSimpleAttribute($document->getIssue(), ['DC.citation.issue', 'citation_issue'], $metas);
        }

        if ($this->isJournalPaper($document) || $this->isConferencePaper($document) || $this->isBookPart($document)) {
            $this->handleSimpleAttribute($document->getPageFirst(), ['DC.citation.spage', 'citation_firstpage'], $metas);
            $this->handleSimpleAttribute($document->getPageLast(), ['DC.citation.epage', 'citation_lastpage'], $metas);
        }

        $this->handleIdentifierDoi($document, $metas);

        if ($this->isJournalPaper($document) ||
            $this->isConferencePaper($document) ||
            $this->isWorkingPaper($document) ||
            $this->isOther($document)) {
            $this->handleIdentifierIssn($document, $metas);
        }

        $this->handleIdentifierIsbn($document, $metas);
        $this->handleKeywords($document, $metas);

        if ($this->isThesis($document)) {
            $this->handleSimpleAttribute($document->getType(), ['citation_dissertation_name'], $metas);
            $this->handleThesisPublisher($document, $metas);
        }

        if ($this->isWorkingPaper($document)) {
            $this->handleInstitution($document, $metas);
        }

        $this->handleSimpleAttribute($document->getLanguage(), ['DC.language', 'citation_language'], $metas);

        if ($this->isConferencePaper($document)) {
            $this->handleConferenceTitle($document, $metas);
        }

        if ($this->isBook($document) || $this->isBookPart($document)) {
            $this->handleBookTitle($document, $metas);
        }

        $this->handleFulltextUrls($document, $metas);
        $this->handleFrontdoorUrl($document, $metas);
        $this->handleAbstracts($document, $metas);
        $this->handleIdentifierUrn($document, $metas);
        $this->handleLicences($document, $metas);
        return $metas;
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleAuthors($document, &$metas)
    {
        foreach ($document->getPersonAuthor() as $author) {
            $lastname = trim($author->getLastName());
            if ($lastname !== '') {
                $name = $lastname;

                $firstname = trim($author->getFirstName());
                if ($firstname !== '') {
                    $name .= ", " . $firstname;
                }

                $metas[] = ['DC.creator', $name];
                $metas[] = ['citation_author', $name];
                $metas[] = ['author', $name];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleDates($document, &$metas)
    {
        $dateStr = null;

        $datePublished = $document->getPublishedDate();
        if (! is_null($datePublished)) {
            $dateStr = $datePublished->getZendDate()->get('yyyy-MM-dd');

        } else {
            $dateStr = $document->getPublishedYear();
        }

        if (! is_null($dateStr)) {
            $metas[] = ["DC.date", $dateStr];
            $metas[] = ["DC.issued", $dateStr];
            $metas[] = ["citation_date", $dateStr];
            $metas[] = ["citation_publication_date", $dateStr];
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleTitles($document, &$metas)
    {
        $subtitlesByLang = [];
        $subtitles = $document->getTitleSub();
        if (! empty($subtitles)) {
            // Aufspaltung der Untertitel nach Sprache (eigentlich darf pro Sprache höchstens
            // ein Untertitel existieren)
            foreach ($subtitles as $subtitle) {
                $subtitleValue = trim($subtitle->getValue());
                if ($subtitleValue !== '') {
                    $lang = $subtitle->getLanguage();
                    if (array_key_exists($lang, $subtitlesByLang)) {
                        // eigentlich kann dieser Fall gar nicht auftreten, wenn die Eingabe der
                        // Untertitel über die Webapplikation geschieht, weil sich mehrere Untertitel
                        // in der gleichen Sprache nicht speichern lassen: für Robustheit wird
                        // dieser Fall hier aber dennoch behandelt
                        $subtitlesByLang[$lang][] = $subtitleValue;
                    }
                    else {
                        $subtitlesByLang[$lang] = [$subtitleValue];
                    }
                }
            }
        }

        foreach ($document->getTitleMain() as $title) {
            $titleValue = trim($title->getValue());
            if ($titleValue !== '') {
                // gibt es einen "zugehörigen" Untertitel in der Sprache des Haupttitels, dann wird
                // der Untertitel mit Doppelpunkt an den Haupttitel angefügt
                $lang = $title->getLanguage();
                if (array_key_exists($lang, $subtitlesByLang)) {
                    $subtitles = $subtitlesByLang[$lang];
                    // i.d.R. enthält $subtitles nur ein Element
                    foreach ($subtitles as $subtitle) {
                        $titleValue .= " : " . $subtitle;
                    }
                }

                $metas[] = ['DC.title', $titleValue];
                $metas[] = ['citation_title', $titleValue];
                $metas[] = ['title', $titleValue];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleJournalTitle($document, &$metas)
    {
        foreach ($document->getTitleParent() as $titleParent) {
            $title = trim($titleParent->getValue());
            if ($title !== '') {
                $metas[] = ['DC.relation.ispartof', $title];
                $metas[] = ['citation_journal_title', $title];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleAbstracts($document, &$metas)
    {
        foreach ($document->getTitleAbstract() as $abstract) {
            $abstractValue = trim($abstract->getValue());
            if ($abstractValue !== '') {
                $metas[] = ['DC.description', $abstractValue];
                $metas[] = ['description', $abstractValue];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleLicences($document, &$metas)
    {
        foreach ($document->getLicence() as $docLicence) {
            $metas[] = ['DC.rights', $docLicence->getModel()->getLinkLicence()];
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleIdentifierUrn($document, &$metas)
    {
        foreach ($document->getIdentifierUrn() as $identifier) {
            $identifierValue = trim($identifier->getValue());
            if ($identifierValue !== '') {
                $metas[] = ['DC.identifier', $identifierValue];
                if (isset($this->config, $this->config->urn->resolverUrl)) {
                    $metas[] = ['DC.identifier', $this->config->urn->resolverUrl . $identifierValue];
                }
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleIdentifierDoi($document, &$metas)
    {
        foreach ($document->getIdentifierDoi() as $identifier) {
            $identifierValue = trim($identifier->getValue());
            if ($identifierValue !== '') {
                $metas[] = ['DC.identifier', $identifierValue];
                $metas[] = ['citation_doi', $identifierValue];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleIdentifierIssn($document, &$metas)
    {
        foreach ($document->getIdentifierIssn() as $identifier) {
            $identifierValue = trim($identifier->getValue());
            if ($identifierValue !== '') {
                $metas[] = ['DC.identifier', $identifierValue];
                $metas[] = ['citation_issn', $identifierValue];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleIdentifierIsbn($document, &$metas)
    {
        foreach ($document->getIdentifierIsbn() as $identifier) {
            $identifierValue = trim($identifier->getValue());
            if ($identifierValue !== '') {
                $metas[] = ['DC.identifier', $identifierValue];
                $metas[] = ['citation_isbn', $identifierValue];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleFrontdoorUrl($document, &$metas)
    {
        $frontdoorUrl = $this->fullUrl . '/frontdoor/index/index/docId/' . $document->getId();
        $metas[] = ['DC.identifier', $frontdoorUrl];
        $metas[] = ['citation_abstract_html_url', $frontdoorUrl];
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleFulltextUrls($document, &$metas)
    {
        if (Application_Xslt::embargoHasPassed($document)) {
            $baseUrlFiles = $this->fullUrl;
            if (isset($this->config, $this->config->deliver->url->prefix)) {
                $baseUrlFiles .= $this->config->deliver->url->prefix;
            }
            else {
                $baseUrlFiles .= '/files';
            }

            foreach ($document->getFile() as $file) {
                if ((! $file->exists())
                    or ($file->getVisibleInFrontdoor() !== '1')
                    or (! Application_Xslt::fileAccessAllowed($file->getId()))) {
                    continue;
                }

                $metas[] = ['DC.identifier', "$baseUrlFiles/" . $document->getId() . "/" . $file->getPathName()];

                $keyName = null;
                switch ($file->getMimeType()) {
                    case 'application/pdf':
                        $keyName = 'citation_pdf_url';
                        break;
                    case 'application/postscript':
                        $keyName = 'citation_ps_url';
                        break;
                    default:
                        $keyName = 'citation_pdf_url';
                        break;
                }
                if (! is_null($keyName)) {
                    $metas[] = [$keyName, "$baseUrlFiles/" . $document->getId() . "/" . $file->getPathName()];
                }
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param $metas array Array mit Metatag-Paaren
     */
    private function handleKeywords($document, &$metas)
    {
        $subjectsArray = [];
        foreach ($document->getSubject() as $subject) {
            $subjectValue = trim($subject->getValue());
            if ($subjectValue !== '') {
                $metas[] = ['DC.subject', $subjectValue];
                $metas[] = ['citation_keywords', $subjectValue];
                $subjectsArray[] = $subjectValue;
            }
        }
        if (! empty($subjectsArray)) {
            $subjectsArray = array_unique($subjectsArray);
            $metas[] = ['keywords', implode(", ", $subjectsArray)];
        }
    }

    /**
     * @param string $value Wert des Metatags
     * @param array $keys Array mit Metatag-Schlüsseln
     * @param array $metas Array mit Metatag-Paaren
     */
    private function handleSimpleAttribute($value, $keys, &$metas)
    {
        $value = trim($value);
        if ($value !== '') {
            foreach ($keys as $key) {
                $metas[] = [$key, $value];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param array $metas Array mit Metatag-Paaren
     */
    private function handleThesisPublisher($document, &$metas)
    {
        foreach ($document->getThesisPublisher() as $publisher) {
            $publisherName = trim($publisher->getName());
            if ($publisherName !== '') {
                $metas[] = ['DC.publisher', $publisherName];
                $metas[] = ['citation_dissertation_institution', $publisherName];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param array $metas Array mit Metatag-Paaren
     */
    private function handleInstitution($document, &$metas)
    {
        $metaValue = trim($document->getCreatingCorporation());
        if ($metaValue === '') {
            $metaValue = trim($document->getContributingCorporation());
        }
        if ($metaValue === '') {
            $metaValue = trim($document->getPublisherName());
        }
        if ($metaValue !== '') {
            $metas[] = ['DC.publisher', $metaValue];
            $metas[] = ['citation_technical_report_institution', $metaValue];
        }
    }

    /**
     * @param Opus_Document $document
     * @param array $metas Array mit Metatag-Paaren
     */
    private function handleConferenceTitle($document, &$metas)
    {
        foreach ($document->getTitleParent() as $title) {
            $titleTrimmed = trim($title->getValue());
            if ($titleTrimmed !== '') {
                $metas[] = ['DC.relation.ispartof', $titleTrimmed];
                $metas[] = ['citation_conference_title', $titleTrimmed];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @param array $metas Array mit Metatag-Paaren
     */
    private function handleBookTitle($document, &$metas)
    {
        foreach ($document->getTitleParent() as $title) {
            $titleTrimmed = trim($title->getValue());
            if ($titleTrimmed !== '') {
                $metas[] = ['DC.relation.ispartof', $titleTrimmed];
                $metas[] = ['citation_inbook_title', $titleTrimmed];
            }
        }
    }

    /**
     * @param Opus_Document $document
     * @return bool
     */
    private function isJournalPaper($document)
    {
        if (isset($this->config, $this->config->metatags->defaultMapping->journal_paper)) {
            if (in_array($document->getType(), $this->config->metatags->defaultMapping->journal_paper->toArray())) {
                return true;
            }
        }
        // Hinzufügen von Nicht-Standard-Dokumenttypen zum Mapping in config.ini
        if (isset($this->config, $this->config->metatags->mapping->journal_paper)) {
            return in_array($document->getType(), $this->config->metatags->mapping->journal_paper->toArray());
        }

        return false;
    }

    /**
     * @param Opus_Document $document
     * @return bool
     */
    private function isConferencePaper($document)
    {
        if (isset($this->config, $this->config->metatags->defaultMapping->conference_paper)) {
            if (in_array($document->getType(), $this->config->metatags->defaultMapping->conference_paper->toArray())) {
                return true;
            }
        }
        // Hinzufügen von Nicht-Standard-Dokumenttypen zum Mapping in config.ini
        if (isset($this->config, $this->config->metatags->mapping->conference_paper)) {
            return in_array($document->getType(), $this->config->metatags->mapping->conference_paper->toArray());
        }
        return false;
    }

    /**
     * @param Opus_Document $document
     * @return bool
     */
    private function isThesis($document)
    {
        if (isset($this->config, $this->config->metatags->defaultMapping->thesis)) {
            if (in_array($document->getType(), $this->config->metatags->defaultMapping->thesis->toArray())) {
                return true;
            }
        }
        // Hinzufügen von Nicht-Standard-Dokumenttypen zum Mapping in config.ini
        if (isset($this->config, $this->config->metatags->mapping->thesis)) {
            return in_array($document->getType(), $this->config->metatags->mapping->thesis->toArray());
        }
        return false;
    }

    /**
     * @param Opus_Document $document
     * @return bool
     */
    private function isWorkingPaper($document)
    {
        if (isset($this->config, $this->config->metatags->defaultMapping->working_paper)) {
            if (in_array($document->getType(), $this->config->metatags->defaultMapping->working_paper->toArray())) {
                return true;
            }
        }
        // Hinzufügen von Nicht-Standard-Dokumenttypen zum Mapping in config.ini
        if (isset($this->config, $this->config->metatags->mapping->working_paper)) {
            return in_array($document->getType(), $this->config->metatags->mapping->working_paper->toArray());
        }
        return false;
    }

    /**
     * @param Opus_Document $document
     * @return bool
     */
    private function isBook($document)
    {
        if (isset($this->config, $this->config->metatags->defaultMapping->book)) {
            if (in_array($document->getType(), $this->config->metatags->defaultMapping->book->toArray())) {
                return true;
            }
        }
        // Hinzufügen von Nicht-Standard-Dokumenttypen zum Mapping in config.ini
        if (isset($this->config, $this->config->metatags->mapping->book)) {
            return in_array($document->getType(), $this->config->metatags->mapping->book->toArray());
        }
        return false;
    }

    /**
     * @param Opus_Document $document
     * @return bool
     */
    private function isBookPart($document)
    {
        if (isset($this->config, $this->config->metatags->defaultMapping->book_part)) {
            if (in_array($document->getType(), $this->config->metatags->defaultMapping->book_part->toArray())) {
                return true;
            }
        }
        // Hinzufügen von Nicht-Standard-Dokumenttypen zum Mapping in config.ini
        if (isset($this->config, $this->config->metatags->mapping->book_part)) {
            return in_array($document->getType(), $this->config->metatags->mapping->book_part->toArray());
        }
        return false;
    }

    /**
     * @param $document Opus_Document
     * @return bool
     */
    private function isOther($document)
    {
        return ! ($this->isJournalPaper($document) ||
            $this->isConferencePaper($document) ||
            $this->isThesis($document) ||
            $this->isWorkingPaper($document) ||
            $this->isBook($document) ||
            $this->isBookPart($document));
    }

}
