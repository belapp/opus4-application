<?xml version="1.0" encoding="UTF-8"?>
<!--
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the North Rhine-Westphalian Library Service Center,
 * the Cooperative Library Network Berlin-Brandenburg, the Saarland University
 * and State Library, the Saxon State Library - Dresden State and University
 * Library, the Bielefeld University Library and the University Library of
 * Hamburg University of Technology with funding from the German Research
 * Foundation and the European Regional Development Fund.
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
 * @author      Edouard Simon <edouard.simon@zib.de>
 * @copyright   Copyright (c) 2009-2011, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */
-->

<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:php="http://php.net/xsl"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:xml="http://www.w3.org/XML/1998/namespace"
                exclude-result-prefixes="php">
   
    <!-- Additional Templates with auxilliary functions. -->
    <!-- -->
    <!-- Named template to proof, what to show for collections, depending on display_frontdoor -->
    <xsl:template name="checkdisplay">
        <xsl:choose>
            <xsl:when test="@RoleDisplayFrontdoor = 'Number' and @Number != ''">
                <xsl:value-of select="@Number" />
            </xsl:when>
            
            <xsl:when test="@RoleDisplayFrontdoor = 'Name' and @Name != ''">
                <xsl:value-of select="@Name" />
            </xsl:when>

            <xsl:when test="@RoleDisplayFrontdoor = 'Number,Name'">
                <xsl:if test="@Number != ''">
                    <xsl:value-of select="@Number" />
                </xsl:if>
                <xsl:if test="@Name != ''">
                    <xsl:if test="@Number != ''">
                        <xsl:text> </xsl:text>
                    </xsl:if>
                    <xsl:value-of select="@Name" />
                </xsl:if>
            </xsl:when>

            <xsl:when test="@RoleDisplayFrontdoor = 'Name,Number'">
                <xsl:if test="@Name != ''">
                    <xsl:value-of select="@Name" />
                </xsl:if>
                <xsl:if test="@Number != ''">
                    <xsl:if test="@Name != ''">
                        <xsl:text> </xsl:text>
                    </xsl:if>
                    <xsl:value-of select="@Number" />
                </xsl:if>
            </xsl:when>
        </xsl:choose>
    </xsl:template>

    <!-- Named template to translate a field's name. Needs no parameter. -->
    <xsl:template name="translateFieldname">
        <xsl:value-of select="php:functionString('Frontdoor_IndexController::translate', name())" />
        <xsl:if test="normalize-space(@Language)">
            <!-- translation of language abbreviations  -->
            <xsl:text> (</xsl:text>
            <xsl:call-template name="translateString">
                <xsl:with-param name="string" select="@Language" />
            </xsl:call-template>
            <xsl:text>)</xsl:text>
        </xsl:if>
        <xsl:text>:</xsl:text>
    </xsl:template>

    <!-- Named template to translate an arbitrary string. Needs the translation key as a parameter. -->
    <xsl:template name="translateString">
        <xsl:param name="string" />
        <xsl:value-of select="php:functionString('Frontdoor_IndexController::translate', $string)" />
    </xsl:template>

    <xsl:template name="translateStringWithDefault">
        <xsl:param name="string" />
        <xsl:param name="default" />
        <xsl:value-of select="php:functionString('Frontdoor_IndexController::translateWithDefault', $string, $default)" />
    </xsl:template>

    <xsl:template name="replaceCharsInString">
        <xsl:param name="stringIn"/>
        <xsl:param name="charsIn"/>
        <xsl:param name="charsOut"/>
        <xsl:choose>
            <xsl:when test="contains($stringIn,$charsIn)">
                <xsl:value-of select="concat(substring-before($stringIn,$charsIn),$charsOut)"/>
                <xsl:call-template name="replaceCharsInString">
                    <xsl:with-param name="stringIn" select="substring-after($stringIn,$charsIn)"/>
                    <xsl:with-param name="charsIn" select="$charsIn"/>
                    <xsl:with-param name="charsOut" select="$charsOut"/>
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$stringIn"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
</xsl:stylesheet>