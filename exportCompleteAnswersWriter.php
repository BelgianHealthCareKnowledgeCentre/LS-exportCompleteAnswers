<?php
/**
 * exportCompleteAnswersWriter part of exportCompleteAnswers Plugin for LimeSurvey
 * Writer for the plugin
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @copyright 2014 Belgian Health Care Knowledge Centre (KCE) <http://kce.fgov.be>
 * @license AGPL v3
 * @version 0.9
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Affero Public License for more details.
 *
 */
Yii::import('application.helpers.admin.export.*');
class exportCompleteAnswersWriter extends CsvWriter {

    public $fieldmap = null;
    public $sLang;
    public $exportAnswerCode,$exportAnswerText,$exportAnswerPosition,$exportAnswerCodeBefore,$exportAnswerCodeAfter,$exportNullEmptyAnswerCode;
    public $codeStringForNull,$textStringForNull;
    public $beforeHeadColumnCode,$afterHeadColumnCode,$beforeHeadColumnFull,$afterHeadColumnFull;
    public $oldHeadFormat;// To keep the selected head format but use own
    public $oSurvey;

    /**
    * Initialization method 
    * Update $oOptions to use own, keep the old headingFormat in $this->oldHeadFormat
    *
    * @param Survey $survey
    * @param mixed $sLanguageCode
    * @param FormattingOptions $oOptions
    */
    public function init(\SurveyObj $oSurvey, $sLanguageCode, \FormattingOptions $oOptions) {

        $this->sLang=$sLanguageCode;
        // Change filename
        $now=date("Ymd-His");

        $this->oSurvey=$oSurvey;
        $this->oldHeadFormat=$oOptions->headingFormat;
        $oOptions->headingFormat = "full";      // force to use own code
        $oOptions->answerFormat = "short";      // force to use own code
        if($this->exportAnswerPosition=='aseperatecodetext' && $this->exportAnswerCode && $this->exportAnswerText)
        {
            // Need to double some $oOptions->selectedColumns
            $aSelectedColumns=array();
            foreach($oOptions->selectedColumns as $sSelectedColumns)
            {
                $aSelectedColumns[]=$sSelectedColumns;
                $sFieldType=$oSurvey->fieldMap[$sSelectedColumns]['type'];
                if( !self::sameTextAndCode($sFieldType,$sSelectedColumns))
                {
                    $aSelectedColumns[]=$sSelectedColumns;
                }

            }
            $oOptions->selectedColumns=$aSelectedColumns;
        }
        parent::init($oSurvey, $sLanguageCode, $oOptions);
        $this->csvFilename = "results-survey_{$oSurvey->id}_{$now}.csv";
    }

    /**
    * Returns the adapted heading using parent function
    *
    * @param Survey $survey
    * @param FormattingOptions $oOptions
    * @param string $fieldName
    * @return string (or false)
    */
    public function getFullHeading(SurveyObj $survey, FormattingOptions $oOptions, $fieldName){
        $sQuestion="";
        static $aColumnDone=array();
        switch ($this->oldHeadFormat)
        {
            case 'abbreviated':
                $sQuestion = parent::getAbbreviatedHeading($survey, $fieldName);
                break;
            case 'full':
                $sQuestion = parent::getFullHeading($survey, $oOptions, $fieldName);
                break;
            default:
            case 'code':
                if (isset($survey->fieldMap[$fieldName])) {
                    $sQuestion = viewHelper::getFieldCode($survey->fieldMap[$fieldName]);
                } else {
                    // Token field
                    $sQuestion = $column;
                }
                break;
        }
        if ($oOptions->headerSpacesToUnderscores)
        {
            $sQuestion = str_replace(' ', '_', $sQuestion);
        }
        if($this->exportAnswerPosition=='aseperatecodetext')
        {
            if(isset($survey->fieldMap[$fieldName]))
            {
                $aField=$survey->fieldMap[$fieldName];
                if(!self::sameTextAndCode($aField['type'],$fieldName))
                {
                    if(!array_key_exists($fieldName,$aColumnDone)){
                        // Code export
                        $sQuestion=$this->beforeHeadColumnCode.$sQuestion.$this->afterHeadColumnCode;
                        $aColumnDone[$fieldName]=1;
                    }else{
                        // Text export
                        $sQuestion=$this->beforeHeadColumnFull.$sQuestion.$this->afterHeadColumnFull;
                        unset($aColumnDone[$fieldName]);
                    }
                }else{
                    $sQuestion=$sQuestion;
                }
            } else {
                $sQuestion=$sQuestion;
            }
        }
        return $sQuestion;
    }

    /**
    * Performs a transformation of the response value.
    * Reload the survey to use own value
    *
    * @param string $sValue
    * @param string $fieldType
    * @param FormattingOptions $oOptions
    * @param string $sColumn The name of the column
    * @return string
    */
    protected function transformResponseValue($sValue, $fieldType, FormattingOptions $oOptions, $sColumn = null)
    {
        static $aColumnDone=array();

        if($this->exportAnswerPosition=='aseperatecodetext')
        {
            if(!array_key_exists($sColumn,$aColumnDone)){
                $bExportAnswerCode=!self::sameTextAndCode($fieldType,$sColumn);
                $bExportAnswerText=self::sameTextAndCode($fieldType,$sColumn);
                $aColumnDone[$sColumn]=1;
            }else{
                $bExportAnswerCode=false;
                $bExportAnswerText=true;
                unset($aColumnDone[$sColumn]);
            }
        }
        else
        {
            $bExportAnswerCode=$this->exportAnswerCode && !self::sameTextAndCode($fieldType,$sColumn);
            $bExportAnswerText=$this->exportAnswerText;
        }
        $sAnswerFull="";
        $sAnswerText="";
        $sAnswerCode="";
        $oSurvey=$this->oSurvey;// We need survey to get answers ...

        if($bExportAnswerCode){
            if(is_null($sValue) )
                $sAnswerCode=$this->codeStringForNull;
            else
                $sAnswerCode=Writer::transformResponseValue($sValue,$fieldType,$oOptions,$sColumn);
            if( $sValue!=""
                || ( $sValue==="" && $this->exportNullEmptyAnswerCode!="notempty" )
                || (is_null($sValue) && $this->exportNullEmptyAnswerCode=="always" )
               )
            {
                if($this->exportAnswerPosition!='aseperatecodetext')
                    $sAnswerCode= $this->exportAnswerCodeBefore.$sAnswerCode.$this->exportAnswerCodeAfter;
            }
        }
        if($bExportAnswerText)
        {
            if(is_null($sValue))
                $sAnswerText=$this->textStringForNull;
            else
                $sAnswerText=Writer::transformResponseValue(
                                $oSurvey->getFullAnswer($sColumn, $sValue, $this->translator, $this->languageCode), 
                                $fieldType, 
                                $oOptions,
                                $sColumn
                                );
                // Remove not needed N/A ....
                $aNaType=array('Y','G','M','P');
                if(in_array($fieldType,$aNaType) && $sAnswerText=="N/A")
                    $sAnswerText="";
        }
        if($this->exportAnswerPosition=='acodetext'){
            $sAnswerFull=$sAnswerCode.$sAnswerText;
        }elseif($this->exportAnswerPosition=='atextcode'){
            $sAnswerFull=$sAnswerText.$sAnswerCode;
        }
        return $sAnswerFull;
    }
    
    /*
    * Get if field need 1 column only : one for code and one for text. If text and code are same, return true
    * @param string $sFieldType : the field type
    * @param string $sFieldName : the field name
    * @return string : NULL/code/text
    */
    public function sameTextAndCode($sFieldType,$sFieldName)
    {
        // Have only code : 5 point, arry 5 point, arry 10 point, language (this one can/must be fixed ?)
        $aOnlyCode=array("5","A","B","I");
        // Have only text : Text and numeric + file upload language (this one can/must be fixed ?)
        $aOnlyText=array("K","N","Q","S","T","U","X",'*',';',':',"|");
        // No field type, but some can have specific answers (date) : actually no difference
        $aDateField=array("submitdate","startdate","datestamp");
        $aInfoField=array("id","lastpage","startlanguage","ipaddr","refurl");
        
        // Have other question type
        $aOtherType=array("L","M","P","!");
        // Have comment question type
        $aCommentType=array("O","P");
        if(in_array($sFieldType,$aOnlyCode))
            return 'code';
        if(in_array($sFieldType,$aOnlyText))
            return 'text';
        if(in_array($sFieldName,$aInfoField) || in_array($sFieldName,$aDateField))
            return 'text';// 'code'
        if(in_array($sFieldType,$aOtherType) && substr_compare($sFieldName, "other", -5, 5) === 0)
            return 'text';
        if(in_array($sFieldType,$aCommentType) && substr_compare($sFieldName, "comment", -7, 7) === 0)
            return 'text';

        // default NULL mean we need 2 column
    }
}
