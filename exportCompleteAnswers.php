<?php
/**
 * exportCompleteAnswers Plugin for LimeSurvey
 * Export code and complete answer in CSV
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014 Denis Chenu <http://sondages.pro>
 * @copyright 2014 Belgian Health Care Knowledge Centre (KCE) <http://kce.fgov.be>
 * @license GPL v3
 * @version 0.9
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */
class exportCompleteAnswers extends PluginBase {
    protected $storage = 'DbStorage';
    static protected $name = 'Export all answers (code and text)';
    static protected $description = 'Allow to export code and text for answers. Give some ability to export it the way you want.';

    protected $settings = array(
        'default'=>array(
            'type'=>'boolean',
            'label'=>'Use as default export',
            'default'=>1,
        ),
        'exportAnswerCode'=>array(
            'type'=>'boolean',
            'label'=>'Export Answer code',
            'default'=>1,
        ),
        'exportAnswerText'=>array(
            'type'=>'boolean',
            'label'=>'Export Answer text',
            'default'=>1,
        ),
        'exportAnswerPosition'=>array(
            'type'=>'select',
            'label'=>'Answer code and text position',
            'options'=>array(
                'acodetext'=>'Code before text',
                'atextcode'=>'Text before code',
                'aseperatecodetext'=>'Code and text in seperate column (code before text)',
            ),
            'default'=>'aseperatecodetext',
        ),
        'exportAnswerCodeBefore'=>array(
            'type'=>'string',
            'label'=>'String before answer code (only if in same column)',
            'default'=>"[",
        ),
        'exportAnswerCodeAfter'=>array(
            'type'=>'string',
            'label'=>'String after answer code (only if in same column)',
            'default'=>"]",
        ),
        'exportNullEmptyAnswerCode'=>array(
            'type'=>'select',
            'label'=>'Export answer condition',
            'options'=>array(
                'notempty'=>'If answered or shown',
                'notnull'=>'If shown to the user', // Not for 2.05 - build 140708
                'always'=>'Always',
            ),
            'default'=>"notnull",
            'help' => 'This settings use code before and after under condition, default adding the string if user see the question (except for numerical and date question).'
        ),
        'textStringForNull'=>array(
            'type'=>'string',
            'label'=>'String for null value for answer text (only if exported)',
            'default'=>"",
        ),
        'codeStringForNull'=>array(
            'type'=>'string',
            'label'=>'String for null value for answer code (only if exported)',
            'default'=>"N/A",
        ),
        'beforeHeadColumnCode'=>array(
            'type'=>'string',
            'label'=>'String before question for answer code column (only for separate columns and if question have different code and text)',
            'default'=>"[code]",
        ),
        'afterHeadColumnCode'=>array(
            'type'=>'string',
            'label'=>'String after question for answer code column (only for separate columns and if question have different code and text)',
            'default'=>"",
        ),
        'beforeHeadColumnFull'=>array(
            'type'=>'string',
            'label'=>'String before question for full answer column (only for separate columns and if question have different code and text)',
            'default'=>"",
        ),
        'afterHeadColumnFull'=>array(
            'type'=>'string',
            'label'=>'String after question for full answer column (only for separate columns)',
            'default'=>"[full answer]",
        ),
    );

    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);
        $this->subscribe('beforeActivate');
        $this->subscribe('listExportPlugins');
        $this->subscribe('listExportOptions');
        $this->subscribe('newExport');
    }
    public function beforeActivate()
    {
        $oEvent = $this->getEvent();
        if (class_exists('exportCodeAndText', false)) {
            $test=get_class_vars('exportCodeAndText');
            if(!isset($test['version']) || $test['version']<2.2)
            {
                $oEvent->set('success', false);
                $oEvent->set('message', gt('This plugin can not be activated if exportCodeAndText plugin less than 2.2 version.')."<pre>".print_r($test,1)."</pre>");
            }
        }
    }
    public function listExportOptions()
    {
        $event = $this->getEvent();
        $type = $event->get('type');

        switch ($type) {
            case 'csv-allanswer':
            default:
                $event->set('label', gT("CSV with all answers part"));
                if($this->get('default',null,null,$this->settings['default']['default']))
                    $event->set('default', true);
                break;
        }
    }
    /**
    * Registers this export type
    */
    public function listExportPlugins()
    {
        $event = $this->getEvent();
        $exports = $event->get('exportplugins');
        $newExport=array('csv'=>$exports['csv'],'csv-allanswer'=>get_class());
        unset($exports['csv']);
        $exports=$newExport+$exports;
        $event->set('exportplugins', $exports);
    }
    public function newExport()
    {
        $event = $this->getEvent();
        $type = $event->get('type');
        switch ($type) {
            case 'csv-allanswer':
            default:
                $writer = new exportCompleteAnswersWriter();
                break;
        }

        foreach ($this->settings as $name => $value)
        {
            $default=isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL;
            $value = $this->get($name,null,null,$default);
            $writer->$name=$value;
        }
        $event->set('writer', $writer);
    }
}