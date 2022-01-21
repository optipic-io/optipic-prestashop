<?php
/**
* 2007-2021 OptiPic
*
* NOTICE OF LICENSE
*
* PrestaShop module to integrate with OptiPic.io service to optimize site images.
*
*  @author    OptiPic.io <info@optipic.io>
*  @copyright 2007-2021 OptiPic
*  @license   http://www.opensource.org/licenses/mit-license.html  MIT License
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

if (class_exists('\optipic\cdn\ImgUrlConverter') == false) {
    include_once dirname(__FILE__).'/classes/ImgUrlConverter.php';
}

class Optipic extends Module
{
    const AUTOREPLACE_ACTIVE = 'OPTIPIC_AUTOREPLACE_ACTIVE';
    const SITE_ID = 'OPTIPIC_SITE_ID';
    const DOMAINS = 'OPTIPIC_DOMAINS';
    const EXCLUSIONS_URL = 'OPTIPIC_EXCLUSIONS_URL';
    const WHITELIST_IMG_URL = 'OPTIPIC_WHITELIST_IMG_URL';
    const SRCSET_ATTRS = 'OPTIPIC_SRCSET_ATTRS';

    public function __construct()
    {
        $this->name = 'optipic';
        $this->version = '1.29.0';
        $this->author = 'OptiPic';
        $this->tab = 'seo';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.6.0.0',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;
        $this->module_key = '9817feda271b846366614babc1785a01';

        parent::__construct();

        $this->displayName = $this->l('OptiPic');
        $this->description = $this->l('OptiPic - image optimization via smart CDN. The module automates the process of optimizing and compressing all images on the site according to the recommendations of Google PageSpeed Insights.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    //install module
    public function install()
    {
        if (!parent::install() ||
            !Configuration::updateValue(self::AUTOREPLACE_ACTIVE, false) ||
            !Configuration::updateValue(self::SITE_ID, '') ||
            !Configuration::updateValue(self::DOMAINS, '') ||
            !Configuration::updateValue(self::EXCLUSIONS_URL, '') ||
            !Configuration::updateValue(self::WHITELIST_IMG_URL, '') ||
            !Configuration::updateValue(self::SRCSET_ATTRS, '') ||
            !$this->registerHook('actionOutputHTMLBefore') ||
            !$this->registerHook('displayBackOfficeHeader')
        ) {
            return false;
        }

        return true;
    }

    //uninstall module
    public function uninstall()
    {
        if (!parent::uninstall()
            || !Configuration::deleteByName(self::AUTOREPLACE_ACTIVE)
            || !Configuration::deleteByName(self::SITE_ID)
            || !Configuration::deleteByName(self::DOMAINS)
            || !Configuration::deleteByName(self::EXCLUSIONS_URL)
            || !Configuration::deleteByName(self::WHITELIST_IMG_URL)
            || !Configuration::deleteByName(self::SRCSET_ATTRS)
        ) {
            return false;
        }

        return true;
    }

    //Configure settings page
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $settings = array();
            $settings['autoreplace_active'] = boolval(Tools::getValue(self::AUTOREPLACE_ACTIVE));
            $settings['site_id']= (string) Tools::getValue(self::SITE_ID);
            $settings['domains']= (string) Tools::getValue(self::DOMAINS);
            $settings['exclusions_url']= (string) Tools::getValue(self::EXCLUSIONS_URL);
            $settings['whitelist_img_urls']= (string) Tools::getValue(self::WHITELIST_IMG_URL);
            $settings['srcset_attrs']= (string) Tools::getValue(self::SRCSET_ATTRS);

            if (!Validate::isGenericName($settings['autoreplace_active'])
                || !Validate::isGenericName($settings['site_id'])
                || !Validate::isGenericName($settings['domains'])
                || !Validate::isGenericName($settings['exclusions_url'])
                || !Validate::isGenericName($settings['whitelist_img_urls'])
                || !Validate::isGenericName($settings['srcset_attrs'])
            ) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                $this->setSettings($settings);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output.$this->displayForm();
    }

    //Display settings form
    public function displayForm()
    {
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fieldsForm = array();
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Module settings'),
            ],
            'input' => [
                [
                    'type' => 'switch',
                    'label' => $this->l('Enable auto-replace image URLs'),
                    'name' => self::AUTOREPLACE_ACTIVE,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    )
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Site ID in your CDN OptiPic account'),
                    'desc' => $this->l('You can find out your website ID in your CDN OptiPic personal account. Add your site to your account if you have not already done so.'),
                    'name' => self::SITE_ID,
                    'size' => 20
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Domain list (if images are loaded via absolute URLs)'),
                    'name' => self::DOMAINS,
                    'cols' => 20,
                    'rows' => 3,
                    'desc' => $this->l('Each on a new line and without specifying the protocol (http/https).'),
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Site pages that do not include auto-change'),
                    'name' => self::EXCLUSIONS_URL,
                    'cols' => 20,
                    'rows' => 3,
                    'desc' => $this->l('Each on a new line and must start with a slash (/).'),
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Replace only URLs of images starting with a mask'),
                    'name' => self::WHITELIST_IMG_URL,
                    'cols' => 20,
                    'rows' => 3,
                    'desc' => $this->l('Each on a new line and must start with a slash (/).'),
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('List of "srcset" attributes'),
                    'name' => self::SRCSET_ATTRS,
                    'cols' => 20,
                    'rows' => 3,
                    'desc' => $this->l('List of tag attributes, in which you need to replace srcset-markup of images. Each on a new line'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;

        // Load current value
        $helper->fields_value[self::AUTOREPLACE_ACTIVE] = Tools::getValue(self::AUTOREPLACE_ACTIVE, Configuration::get(self::AUTOREPLACE_ACTIVE));
        $helper->fields_value[self::SITE_ID] = Tools::getValue(self::SITE_ID, Configuration::get(self::SITE_ID));
        $helper->fields_value[self::DOMAINS] = Tools::getValue(self::DOMAINS, Configuration::get(self::DOMAINS));
        $helper->fields_value[self::EXCLUSIONS_URL] = Tools::getValue(self::EXCLUSIONS_URL, Configuration::get(self::EXCLUSIONS_URL));
        $helper->fields_value[self::WHITELIST_IMG_URL] = Tools::getValue(self::WHITELIST_IMG_URL, Configuration::get(self::WHITELIST_IMG_URL));
        $helper->fields_value[self::SRCSET_ATTRS] = Tools::getValue(self::SRCSET_ATTRS, Configuration::get(self::SRCSET_ATTRS));
        
        
        
        if (empty($helper->fields_value[self::SRCSET_ATTRS])) {
            $helper->fields_value[self::SRCSET_ATTRS] = implode(PHP_EOL, \optipic\cdn\ImgUrlConverter::getDefaultSettings('srcset_attrs'));
        }
        
        if (empty($helper->fields_value[self::DOMAINS])) {
            $helper->fields_value[self::DOMAINS] = implode(PHP_EOL, \optipic\cdn\ImgUrlConverter::getDefaultSettings('domains'));
        }

        return $helper->generateForm($fieldsForm);
    }

    //get module settings
    public function getSettings()
    {
        return array(
            'autoreplace_active' => Configuration::get(self::AUTOREPLACE_ACTIVE, false),
            'site_id' => Configuration::get(self::SITE_ID, ''),
            'domains' => Configuration::get(self::DOMAINS, ''),
            // !='' ? explode("\n", str_replace(array("\r\n", "\n", "\r"), PHP_EOL, Configuration::get(self::DOMAINS, ''))) : array(),
            'exclusions_url' => Configuration::get(self::EXCLUSIONS_URL, ''),
            //!='' ? explode("\n", str_replace(array("\r\n", "\n", "\r"), PHP_EOL, Configuration::get(self::EXCLUSIONS_URL, ''))) : array(),
            'whitelist_img_urls' => Configuration::get(self::WHITELIST_IMG_URL, ''),
            //!='' ? explode("\n", str_replace(array("\r\n", "\n", "\r"), PHP_EOL, Configuration::get(self::WHITELIST_IMG_URL, ''))) : array(),
            'srcset_attrs' => Configuration::get(self::SRCSET_ATTRS, ''),
            //!='' ? explode("\n", str_replace(array("\r\n", "\n", "\r"), PHP_EOL, Configuration::get(self::SRCSET_ATTRS, ''))) : array(),
        );
    }

    //set module settings from array
    public function setSettings($settings)
    {
        Configuration::updateValue(self::AUTOREPLACE_ACTIVE, $settings['autoreplace_active']);
        Configuration::updateValue(self::SITE_ID, $settings['site_id']);
        Configuration::updateValue(self::DOMAINS, $settings['domains']);
        Configuration::updateValue(self::EXCLUSIONS_URL, $settings['exclusions_url']);
        Configuration::updateValue(self::WHITELIST_IMG_URL, $settings['whitelist_img_urls']);
        Configuration::updateValue(self::SRCSET_ATTRS, $settings['srcset_attrs']);
    }

    public function hookActionOutputHTMLBefore(array $params)
    {
        $settings = $this->getSettings();

        if ($settings['autoreplace_active'] && $settings['site_id']!='') {
            \optipic\cdn\ImgUrlConverter::loadConfig($settings);
            $params['html'] = \optipic\cdn\ImgUrlConverter::convertHtml($params['html']);
        }
    }
    
    public function hookDisplayBackOfficeHeader(array $params = [])
    {
        if (Tools::getValue('controller')=='AdminModules' && Tools::getValue('configure')=='optipic') {
            $currentHost = explode(":", $_SERVER['HTTP_HOST']);
            $currentHost = trim($currentHost[0]);
            
            $settings = $this->getSettings();
            
            if ($currentHost) {
                Media::addJsDef(array(
                    'optipicCurrentHost' => $currentHost,
                    'optipicSid' => $settings['site_id'],
                    'optipicVersion' => $this->version,
                    'optipicSource' => \optipic\cdn\ImgUrlConverter::getDownloadSource(),
                ));
                
                $this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/views/js/settings.js');
            }
        }
    }
}
