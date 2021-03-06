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

    // Google Tag Manager parameters
    $gtmInsert = $params->get('gtmInsert', '0');
    $gtmContainer = $params->get('gtmContainer', '');
    $gtmSettings = array();
    if ( $gtmInsert && !empty($gtmContainer) ) {
      $gtmSettings = array(
                        'container' => $gtmContainer,
                        'code' => $params->get('gtmCode', 'javascript')
                      );
    }

    // Google parameters
    $gaInsert = $params->get('gaInsert', '0');
    $gaProperty = $params->get('gaProperty', '');
    $gaSettings = array();
    if ( $gaInsert && !empty($gaProperty) ) {
      $gaSettings = array(
                        'property' => $gaProperty,
                        'code' => $params->get('gaCode', 'analytics'),
                        'anonymize' => $params->get('gaAnonymize', '1'),
                        'downloads' => $params->get('gaDownloads', 'none')
                      );
    }

    // Matomo parameters
    $maInsert = $params->get('maInsert', '0');
    $maServer = $params->get('maServer', '');
    $maSiteId = $params->get('maSiteId', '');
    $maCookies = $params->get('maCookies', '1');
    $host = '//'.parse_url($maServer, PHP_URL_HOST);
    $fragments = array_filter(explode('/', parse_url($maServer, PHP_URL_PATH)), function($value) { return ($value != '' && strpos($value,'.') === false); });
    $server = $host.'/'.implode('/', $fragments);
    $maSettings = array();
    if ( $maInsert && !empty($maServer) && !empty($maSiteId) ) {
      $maSettings = array(
                        'server' => $server,
                        'siteid' => $maSiteId,
                        'cookies' => $maCookies,
                        'code' => $params->get('maCode', 'javascript')
                      );
    }

    // Media configuration: extensions
    $com_media_params = JComponentHelper::getParams('com_media');
    $upload_extensions = $com_media_params->get('upload_extensions');

    // All parameters
    $this->analytics = array();

    if ( !empty($gtmSettings) || !empty($gaSettings) || !empty($maSettings) ) {
      $this->analytics['cookie']['name'] = 'pb-analytics-disable';
      $this->analytics['optout'] = $params->get('optout', '1');
      $this->analytics['extensions'] = $upload_extensions;
      $this->analytics['gtm'] = $gtmSettings;
      $this->analytics['ga'] = $gaSettings;
      $this->analytics['ma'] = $maSettings;
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
    // fast fail
    if ($this->app->isAdmin() || empty($this->analytics) ) {
        return;
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
    // fast fail
    if ($this->app->isAdmin() || empty($this->analytics) ) {
        return;
    }

    $cookie = $this->analytics['cookie']['name'];
    $insert = '';

    // Google Tag Manager
    if ( $this->analytics['gtm'] ) {
      $insert .= "<!-- Google Tag Manager-->\n";

      switch ($this->analytics['gtm']['code']) {
        case 'iframe':
          $insert .= "<noscript>";
          $insert .= "<iframe src='https://www.googletagmanager.com/ns.html?id=".$this->analytics['gtm']['container']."' height='0' width='0' style='display:none;visibility:hidden'></iframe>";
          $insert .= "</noscript>";
          break;
        
        case 'javascript':
        default:
          $insert .= "<script>\n";
          $insert .= "  if (document.cookie.indexOf('".$cookie."=true') == -1) {\n";
          $insert .= "    console.log('Analytics, Track: gtm.js');\n";
          $insert .= "    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
          $insert .= "    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
          $insert .= "    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
          $insert .= "    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
          $insert .= "    })(window,document,'script','dataLayer','".$this->analytics['gtm']['container']."');\n";
          $insert .= "  }\n";
          $insert .= "</script>\n";
          break;
      }

      $insert .= "<!-- End Google Tag Manager -->\n\n";
    }

    // Google Tracking
    if ( $this->analytics['ga'] ) {

      // Track download links as page view
      $selector = array();
      if ( $this->analytics['ga']['downloads'] == 'view' && !empty($this->analytics['extensions']) ) {
        $extensions = explode(',', $this->analytics['extensions']);
        foreach ($extensions as $ext) {
            array_push($selector, 'a[href$=".'.$ext.'"]');
        }
        $selector = implode(', ', $selector);
      }

      $insert .= "<!-- Google Tracking-->\n";

      switch ($this->analytics['ga']['code']) {
        case 'gtag':
          $insert .= "<script>\n";
          $insert .= "  if (document.cookie.indexOf('".$cookie."=true') == -1) {\n";
          $insert .= "    console.log('Analytics, Track: gtag.js');\n";
          $insert .= "    (function(w,d,s,i) {w.dataLayer=w.dataLayer||[];g=d.createElement(s),m=d.getElementsByTagName(s)[0];\n";
          $insert .= "    g.async=true;g.src='https://www.googletagmanager.com/gtag/js?id='+i; m.parentNode.insertBefore(g,m);\n";
          $insert .= "    })(window,document,'script','".$this->analytics['ga']['property']."');\n";
          $insert .= "    function gtag(){dataLayer.push(arguments);}\n";
          $insert .= "    gtag('js', new Date());\n";
          $insert .= $this->analytics['ga']['anonymize'] == "0" ? "    gtag('config', '".$this->analytics['ga']['property']."');\n" : "    gtag('config', '".$this->analytics['ga']['property']."', { 'anonymize_ip': true });\n";

          // Track download links as page view
          if ( !empty($selector) ) {
            $insert .= "    var elements = document.querySelectorAll('".$selector."');\n";
            $insert .= "    for (var i = 0; i < elements.length; i++) {\n";
            $insert .= "      elements[i].addEventListener('click', function() {\n";
            $insert .= "        gtag('config', '".$this->analytics['ga']['property']."', {'page_path': this.href.replace(/^.*\/\/[^\/]+/, ''), 'page_title': this.text});\n";
            $insert .= "      });\n";
            $insert .= "    }\n";
          }

          $insert .= "  }\n";
          $insert .= "</script>\n";
          break;
        
        case 'analytics':
        default:
          $insert .= "<script>\n";
          $insert .= "  if (document.cookie.indexOf('".$cookie."=true') == -1) {\n";
          $insert .= "    console.log('Analytics, Track: analytics.js');\n";
          $insert .= "    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){\n";
          $insert .= "    (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),\n";
          $insert .= "    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)\n";
          $insert .= "    })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');\n";
          $insert .= "    ga('create', '".$this->analytics['ga']['property']."', 'auto');\n";
          $insert .= $this->analytics['ga']['anonymize'] == "0" ? "" : "    ga('set', 'anonymizeIp', true);\n";
          $insert .= "    ga('send', 'pageview');\n";

          // Track download links as page view
          if ( !empty($selector) ) {
            $insert .= "    var elements = document.querySelectorAll('".$selector."');\n";
            $insert .= "    for (var i = 0; i < elements.length; i++) {\n";
            $insert .= "      elements[i].addEventListener('click', function() {\n";
            $insert .= "        ga('send', 'pageview', {'page': this.href.replace(/^.*\/\/[^\/]+/, ''),'title': this.text});\n";
            $insert .= "      });\n";
            $insert .= "    }\n";
          }

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
          $insert .= "<script>\n";
          $insert .= "  if (document.cookie.indexOf('".$cookie."=true') == -1) {\n";
          $insert .= "    console.log('Analytics, Track: piwik.js');\n";
          $insert .= "    var _paq = window._paq || [];\n";
          if ($this->analytics['ma']['cookies'] == '0') $insert .= "    _paq.push(['disableCookies']);\n";
          $insert .= "    _paq.push(['trackPageView']);\n";
          $insert .= "    _paq.push(['enableLinkTracking']);\n";
          $insert .= "    (function() {\n";
          $insert .= "      var u='".$this->analytics['ma']['server']."';\n";
          $insert .= "      _paq.push(['setTrackerUrl', u+'/piwik.php']);\n";
          $insert .= "      _paq.push(['setSiteId', ".$this->analytics['ma']['siteid']."]);\n";
          $insert .= "      var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];\n";
          $insert .= "      g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'/piwik.js'; s.parentNode.insertBefore(g,s);\n";
          $insert .= "    })();\n";
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
    // fast fail
    if ($this->app->isAdmin() || empty($this->analytics) || !$this->analytics['optout'] || !in_array($context, array('com_content.article')) || JString::strpos($row->text, '{plg_system_pbanalytics_optout') === false ) {
        return;
    }

    // Load language from site
    $lang = JFactory::getLanguage();
    $lang->load('plg_'.$this->_type.'_'.$this->_name, JPATH_SITE);

    // Replacement
    $insert = '<a href="javascript:pbAnalyticsOptOut();" id="analyticsOptOut">'.JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_DISABLE').'</a><span id="analyticsStatus">'.JText::_('PLG_SYSTEM_PBANALYTICS_PRIVACY_ENABLED').'</span>';

    // Replace shortcode with opt out-link
    $regex = '/{plg_system_pbanalytics_optout}/im';
    $row->text = preg_replace($regex, $insert, $row->text);
  
  }

}
