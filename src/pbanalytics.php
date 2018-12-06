<?php
/**
 * @package    PB Analytics
 *
 * @author     Sebastian Brümmer <sebastian@produktivbuero.de>
 * @copyright  Copyright (C) 2018 *produktivbüro . All rights reserved
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * PbAnalytics plugin.
 *
 * @package  PB Analytics
 * @since    0.9.0
 */
class plgSystemPbAnalytics extends CMSPlugin
{
  /**
   * Application object
   *
   * @var    CMSApplication
   * @since  0.9.0
   */
  protected $app;

  /**
   * Affects constructor behavior. If true, language files will be loaded automatically.
   *
   * @var    boolean
   * @since  0.9.0
   */
  protected $autoloadLanguage = true;

  /**
   * This function is called on initialization.
   *
   * @return  void.
   *
   * @since   0.9.0
   */

  public function __construct(&$subject, $config = array())
  {

    parent::__construct($subject, $config);

    $params = new JRegistry($config['params']);

    $this->analytics = array();

    // Basic parameters
    $this->analytics['cookie']['name'] = 'pb-analytics-disable'; // fixed
    $this->analytics['optout'] = $params->get('optout', '1');

    // Google parameters
    $gaInsert = $params->get('gaInsert', '0');
    $gaProperty = $params->get('gaProperty', '');

    $this->analytics['ga'] = array();
    if ( $gaInsert && !empty($gaProperty) ) {
      $this->analytics['ga'] = array(
                        'property' => $gaProperty,
                        'code' => $params->get('gaCode', 'analytics'),
                        'anonymize' => $params->get('gaAnonymize', '1')
                      );
    }

    // Matomo parameters
    $maInsert = $params->get('maInsert', '0');
    $maServer = $params->get('maServer', '');
    $maSiteId = $params->get('maSiteId', '');
    
    $host = '//'.parse_url($maServer, PHP_URL_HOST);
    $fragments = array_filter(explode('/', parse_url($maServer, PHP_URL_PATH)), function($value) { return ($value != '' && strpos($value,'.') === false); });
    $server = $host.'/'.implode('/', $fragments);
    
    $this->analytics['ma'] = array();
    if ( $maInsert && !empty($maServer) && !empty($maSiteId) ) {
      $this->analytics['ma'] = array(
                        'server' => $server,
                        'siteid' => $maSiteId,
                        'code' => $params->get('maCode', 'javascript')
                      );
    }
      
  }

  /**
   * This event is triggered before the framework creates the head section of the Document.
   *
   * @return  void.
   *
   * @since   0.9.0
   */
  public function onBeforeCompileHead()
  {

    if ($this->app->getName() != 'site') {
        return true;
    }

    $doc = JFactory::getDocument();

    $settings = array();

    // Plugin parameters
    $settings = $this->analytics;

    // Language strings
    $settings['link']['disable'] = JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_DISABLE');
    $settings['link']['enable'] = JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_ENABLE');
    $settings['status']['disabled'] = JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_DISABLED');
    $settings['status']['enabled'] = JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_ENABLED');

    // Insert global settings object
    $script = 'window.pb = window.pb || {}; window.pb.analytics = '. json_encode($settings, JSON_FORCE_OBJECT);
    $doc->addScriptDeclaration( $script );
  }

  /**
   * This event is triggered after the framework has rendered the application.
   * When this event is triggered the output of the application is available in the response buffer.
   *
   * @return  void.
   *
   * @since   0.9.0
   */
  public function onAfterRender()
  {

    if ($this->app->getName() != 'site') {
        return true;
    }

    $cookie = $this->analytics['cookie']['name'];
    $insert = '';

    // Google Tracking
    if ( $this->analytics['ga'] ) {
      $insert .= "<!-- Google Tracking-->\n";

      switch ($this->analytics['ga']['code']) {
        case 'gtag':
          $insert .= "<script async src='https://www.googletagmanager.com/gtag/js?id=".$this->analytics['ga']['property']."'></script>\n";
          $insert .= "<script>\n";
          $insert .= "  if (document.cookie.indexOf('".$cookie."=true') == -1) {\n";
          $insert .= "    console.log('Analytics, Track: gtag.js');\n";
          $insert .= "    window.dataLayer = window.dataLayer || [];\n";
          $insert .= "    function gtag(){dataLayer.push(arguments);}\n";
          $insert .= "    gtag('js', new Date());\n";
          $insert .= $this->analytics['ga']['anonymize'] == "0" ? "    gtag('config', '".$this->analytics['ga']['property']."');\n" : "    gtag('config', '".$this->analytics['ga']['property']."', { 'anonymize_ip': true });\n";
          $insert .= "  }\n";
          $insert .= "</script>\n";
          break;
        
        case 'analytics':
        default:
          $insert .= "<script async src='https://www.google-analytics.com/analytics.js'></script>\n";
          $insert .= "<script>\n";
          $insert .= "  if (document.cookie.indexOf('".$cookie."=true') == -1) {\n";
          $insert .= "    console.log('Analytics, Track: analytics.js');\n";
          $insert .= "    window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;\n";
          $insert .= "    ga('create', '".$this->analytics['ga']['property']."', 'auto');\n";
          $insert .= $this->analytics['ga']['anonymize'] == "0" ? "" : "    ga('set', 'anonymizeIp', true);\n";
          $insert .= "    ga('send', 'pageview');\n";
          $insert .= "  }\n";
          $insert .= "</script>\n";
          break;
      }

      $insert .= "<!-- End Google Tracking -->\n\n";
    }

    // Matomo Tracking
    if ( $this->analytics['ma'] ) {
      $insert .= "<!-- Matomo Tracking-->\n";
      
      switch ($this->analytics['ma']['code']) {
        case 'image':
          $insert .= '<img src="'.$this->analytics['ma']['server'].'/piwik.php?idsite='.$this->analytics['ma']['siteid'].'&rec=1" style="border:0" alt="" />'."\n";
          break;
        
        case 'javascript':
        default:
          $insert .= "<script async src='".$this->analytics['ma']['server']."/piwik.js'></script>\n";
          $insert .= "<script>\n";
          $insert .= "  if (document.cookie.indexOf('".$cookie."=true') == -1) {\n";
          $insert .= "    console.log('Analytics, Track: piwik.js');\n";
          $insert .= "    var _paq = _paq || [];\n";
          $insert .= "    _paq.push(['trackPageView']);\n";
          $insert .= "    _paq.push(['enableLinkTracking']);\n";
          $insert .= "    _paq.push(['setTrackerUrl', '".$this->analytics['ma']['server']."/piwik.php']);\n";
          $insert .= "    _paq.push(['setSiteId', ".$this->analytics['ma']['siteid']."]);\n";
          $insert .= "  }\n";
          $insert .= "</script>\n";
          break;
      }

      $insert .= "<!-- End Matomo Tracking -->\n\n";
    }

    // Basic scripts
    $insert .= "<script async src='".JURI::base(true)."/media/plg_system_pbanalytics/js/basics.js'></script>\n";

    $buffer = $this->app->getBody();
    $buffer = str_ireplace('</body>', $insert.'</body>', $buffer);
    $this->app->setBody($buffer);
  }

  /**
   * This is the first stage in preparing content for output and is the
   * most common point for content orientated plugins to do their work.
   *
   * @param   string   $context  The context of the content being passed to the plugin.
   * @param   object   &$row     The article object.  Note $article->text is also available
   * @param   mixed    &$params  The article params
   * @param   integer  $page     The 'page' number
   *
   * @return  void.
   *
   * @since   0.9.0
   */
  public function onContentPrepare($context, &$row, &$params, $page = 0)
  {

    if ($this->app->getName() != 'site') {
        return true;
    }

    // Load language from site
    $lang = JFactory::getLanguage();
    $lang->load('plg_'.$this->_type.'_'.$this->_name, JPATH_SITE);

    $insert = '';

    // Replace shortcode with opt out-link
    if ( $this->analytics['optout'] && ( $this->analytics['ga'] || $this->analytics['ma'] ) ) {
      $insert = '<a href="javascript:pbAnalyticsOptOut();" id="analyticsOptOut">'.JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_DISABLE').'</a><span id="analyticsStatus">'.JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_ENABLED').'</span>';
    }    

    $regex = '/{plg_system_pbanalytics_optout}/im';
    $row->text = preg_replace($regex, $insert, $row->text);
  
  }

}
