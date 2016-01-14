<?php
/**
 * exportCompleteAnswers Plugin for LimeSurvey
 * Export code and complete answer in CSV
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2014-2015 Denis Chenu <http://sondages.pro>
 * @copyright 2014-2015 Belgian Health Care Knowledge Centre (KCE) <http://kce.fgov.be>
 * @license AGPL v3
 * @version 0.9.1
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
class exportCompleteAnswers extends PluginBase {
    protected $storage = 'DbStorage';
    static protected $name = 'Export all answers (code and text)';
    static protected $description = 'Allow to export code and text for answers. Give some ability to export it the way you want.';

    protected $settings = array(
        'title'=>array(
            'type'=>'string',
            'label'=>'Export Name',
            'default'=>'CSV from exportCompleteAnswers',
        ),
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
        'textStringForNull'=>array(
            'type'=>'string',
            'label'=>'String for null value for answer text',
            'default'=>"",
        ),
        'codeStringForNull'=>array(
            'type'=>'string',
            'label'=>'String for null value for answer code',
            'default'=>"N/A",
        ),
        /* Only for same column */
        'sameColumnInfo'=>array(
            'type'=>'info',
            'content' => '<legend style="display:bock">Options if answer and code are in same columns</legend>',
        ),
        'exportAnswerCodeBefore'=>array(
            'type'=>'string',
            'label'=>'String before answer code',
            'default'=>"[",
        ),
        'exportAnswerCodeAfter'=>array(
            'type'=>'string',
            'label'=>'String after answer code',
            'default'=>"]",
        ),
        'exportNullEmptyAnswerCode'=>array(
            'type'=>'select',
            'label'=>'Condition for adding string to code',
            'options'=>array(
                'notempty'=>'If answered',
                'notnull'=>'If shown to the user', // Not for 2.05 - build 140708
                'always'=>'Always',
            ),
            'default'=>"notnull",
            'help' => 'This settings use code before and after under condition, default adding the string if user see the question (except for numerical and date question).'
        ),
        /* Only for 2 columns */
        'differentColumnInfo'=>array(
            'type'=>'info',
            'content' => '<legend style="display:bock">Options if answer and code are in seperate columns.</legend><p>Using 2 column only of needed : code and text are different ( example: for list question or array question type, or for multi choice question type).</p>',
        ),
        'beforeHeadColumnCode'=>array(
            'type'=>'string',
            'label'=>'String before question for answer code column',
            'default'=>"[code]",
        ),
        'afterHeadColumnCode'=>array(
            'type'=>'string',
            'label'=>'String after question for answer code column',
            'default'=>"",
        ),
        'beforeHeadColumnFull'=>array(
            'type'=>'string',
            'label'=>'String before question for full answer column',
            'default'=>"",
        ),
        'afterHeadColumnFull'=>array(
            'type'=>'string',
            'label'=>'String after question for full answer column',
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
                $event->set('label',$this->get('title',null,null,$this->settings['title']['default']));
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
