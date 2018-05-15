<?php

namespace IntelligentSpark\EventSubmission\Module;

use Contao\Events;

/**
 * Class ModuleEventReader
 *
 * Front end module "event reader".
 * @copyright  Leo Feyer 2005-2013
 * @author     Leo Feyer <https://contao.org>
 * @package    Controller
 */

class ModuleEventSubmission extends Events
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_event_submission';


	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### EVENT SUBMISSION ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}


	/**
	 * Generate the module
	 */
	protected function compile()
	{

		global $objPage;
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/event_submission/html/jquery-ui/jquery-ui.min.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/event_submission/html/moment.min.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/event_submission/html/jquery-timepicker-master/jquery.timepicker.min.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/event_submission/html/datepair/dist/datepair.min.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/event_submission/html/datepair/dist/jquery.datepair.min.js';
        $GLOBALS['TL_CSS'][] = 'system/modules/event_submission/html/jquery-timepicker-master/jquery.timepicker.css';
        $GLOBALS['TL_CSS'][] = 'system/modules/event_submission/html/jquery-ui/jquery-ui.min.css';

        $GLOBALS['TL_MOOTOOLS'][] = "<script> jQuery(document).ready(function(){
                    (function($) {

         $('#tl_event_submission .time').timepicker({
            'showDuration': true,
            'timeFormat': 'g:i a'
        });

        $('#tl_event_submission .date').datepicker({
            'format': 'MM/DD/YYYY',
            'autoclose': true
        });

        // initialize datepair

        $('#tl_event_submission').datepair();

     })(jQuery);
    });
    </script>
    ";

        $this->tableless = true;

        $this->loadLanguageFile('tl_calendar_events');
        $this->loadDataContainer('tl_calendar_events');

        $this->Template->fields = '';
        $this->Template->tableless = $this->tableless;
        $doNotSubmit = false;

        $arrEvent = array();
        $arrFields = array();

        $arrEditable = array(
            'title','location','startDate','endDate','startTime','endTime','details','url','singleSRC','name','email','phone'
        );

        $hasUpload = false;
        $i = 0;

        foreach($arrEditable as $field)
        {
            $arrData = $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field];

            if($field=='url') {
                $arrData['label'] = $GLOBALS['TL_LANG']['tl_calendar_events']['event_url'];
                $arrData['eval']['mandatory'] = false;
            }

            $strClass = $GLOBALS['TL_FFL'][$arrData['inputType']];

            // Continue if the class is not defined
            if (!$this->classFileExists($strClass))
            {
                continue;
            }

            $arrData['eval']['tableless'] = $this->tableless;
            $arrData['eval']['required'] = $arrData['eval']['mandatory'];

            switch($field)
            {
                case 'startDate':
                    $arrData['eval']['class'] = 'date start';
                    break;
                case 'endDate':
                    $arrData['eval']['class'] = 'date end';
                    break;
                case 'startTime':
                    $arrData['eval']['class'] = 'time start';
                    if($this->Input->post('FORM_SUBMIT') == 'tl_event_submission')
                        $arrData['eval']['rgxp'] = '';
                    break;
                case 'endTime':
                    $arrData['eval']['class'] = 'time end';
                    if($this->Input->post('FORM_SUBMIT') == 'tl_event_submission')
                        $arrData['eval']['rgxp'] = '';
                    break;
            }

            $objWidget = new $strClass($this->prepareForWidget($arrData, $field, $arrData['default']));
            $objWidget->storeValues = true;
            $objWidget->rowClass = 'row_' . $i . (($i == 0) ? ' row_first' : '') . ((($i % 2) == 0) ? ' even' : ' odd');

            // Validate input
            if ($this->Input->post('FORM_SUBMIT') == 'tl_event_submission')
            {
                $objWidget->validate();
                $varValue = $objWidget->value;

                $rgxp = $arrData['eval']['rgxp'];

                // Convert date formats into timestamps (check the eval setting first -> #3063)
                if (($rgxp == 'date' || $rgxp == 'time' || $rgxp == 'datim') && $varValue != '')
                {
                    try
                    {
                        $objDate = new Date($varValue);
                        $varValue = $objDate->tstamp;
                    }
                    catch (Exception $e)
                    {
                        $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $varValue));
                    }
                }

                // Make sure that unique fields are unique (check the eval setting first -> #3063)
                if ($arrData['eval']['unique'] && $varValue != '')
                {
                    $objUnique = $this->Database->prepare("SELECT * FROM tl_calendar_events WHERE " . $field . "=?")
                        ->limit(1)
                        ->execute($varValue);

                    if ($objUnique->numRows)
                    {
                        $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['unique'], (strlen($arrData['label'][0]) ? $arrData['label'][0] : $field)));
                    }
                }

                if ($objWidget->hasErrors())
                {
                    $doNotSubmit = true;
                }

                // Store current value
                elseif ($objWidget->submitInput())
                {
                    $arrEvent[$field] = $varValue;
                }
            }

            if ($objWidget instanceof uploadable)
            {
                $hasUpload = true;
            }

            $temp = $objWidget->parse();

            $this->Template->fields .= $temp;
            //$arrFields[$arrData['eval']['feGroup']][$field] .= $temp;

            ++$i;
        }

        $this->Template->action = $this->Environment->request;
        $this->Template->slabel = 'Submit';
        $this->Template->formId = 'tl_event_submission';
        $this->Template->rowLast = 'row_' . ++$i . ((($i % 2) == 0) ? ' even' : ' odd');
        $this->Template->enctype = $hasUpload ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
        $this->Template->hasError = $doNotSubmit;

        // Create new user if there are no errors
        if ($this->Input->post('FORM_SUBMIT') == 'tl_event_submission' && !$doNotSubmit)
        {
            $this->createNewEvent($arrEvent);

            $this->jumpToOrReload($this->jumpTo);
        }

	}

    protected function createNewEvent($arrData)
    {
        $arrCal = deserialize($this->cal_calendar,true);

        $arrData['tstamp'] = time();
        $arrData['pid'] = (integer)current($arrCal);
        $arrData['author'] = 1; //Administrator
        $arrData['details'] = '<p>'.$arrData['details'].'</p>'."\n\nlink: <a href=\"".$arrData['url']."\" title=\"".$arrData['title']."\" target=\"_blank\">Event Webpage</a>";

        if($arrData['startTime']) {
            $arrData['addTime'] = 1;
            $arrData['startTime'] = strtotime($arrData['startTime']);
            $arrData['endTime'] = strtotime($arrData['endTime']);
        }

        $arrData['alias'] = standardize($this->restoreBasicEntities($arrData['title']));

        // Create Event
        $objNewEvent = $this->Database->prepare("INSERT INTO tl_calendar_events %s")
            ->set($arrData)
            ->execute();

        #var_dump($objNewEvent);
        $insertId = $objNewEvent->insertId;

        // Inform admin if no activation link is sent
        $this->sendAdminNotification($insertId, $arrData);
    }

    /**
     * Send an admin notification e-mail
     * @param integer
     * @param array
     */
    protected function sendAdminNotification($intId, $arrData)
    {
        $this->loadLanguageFile('tl_calendar_events');

        $objEmail = new Email();

        $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
        $objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'];
        $objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['adminSubject'], $this->Environment->host);

        $strData = "\n\n";

        // Add user details
        foreach ($arrData as $k=>$v)
        {
            if ($k == 'tstamp' || $k == 'author' || $k=='url')
            {
                continue;
            }

            $v = deserialize($v);

            if ((stripos($k,'date')!==false || stripos($k,'time')!==false) && strlen($v))
            {
                $v = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $v);
            }

            if(array_key_exists($k,$GLOBALS['TL_LANG']['tl_calendar_events']))
                $strData .= $GLOBALS['TL_LANG']['tl_calendar_events'][$k][0] . ': ' . (is_array($v) ? implode(', ', $v) : $v) . "\n";
        }

        $strDataFinal = sprintf("A new event has been submitted to the website \n event id: %s \n %s", $intId, $strData . "\n") . "\n";

        $objTemplate = new FrontendTemplate('email_event_submission_notify');

        $objEmail->text = $objTemplate->parse();
        $objEmail->text = "\n".$strDataFinal;
        $objEmail->sendBcc('web@brightcloudstudio.com');
		$objEmail->sendCc('info@westfieldbiz.org');
        $objEmail->sendTo($GLOBALS['TL_ADMIN_EMAIL']);//$GLOBALS['TL_ADMIN_EMAIL']);

        $this->log('A new event (ID ' . $intId . ') has been submitted on the website', 'ModuleEventSubmission sendAdminNotification()', TL_ACCESS);

    }
}

?>