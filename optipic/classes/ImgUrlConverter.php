<?php
/**
 * OptiPic CDN library to convert image urls contains in html/text data
 *
 * @author optipic.io
 * @copyright (c) 2021, https://optipic.io
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @package https://github.com/optipic-io/optipic-cdn-php
 */

namespace optipic\cdn;

class ImgUrlConverter
{
    
    /**
     * Library version number
     */
    const VERSION = '1.26';
    
    /**
     * ID of your site on CDN OptiPic.io service
     */
    public static $siteId = 0;
    
    /**
     * List of domains should replace to cdn.optipic.io
     */
    public static $domains = array();
    
    /**
     * List of URL exclusions - where is URL should not converted
     */
    public static $exclusionsUrl = array();
    
    /**
     * Whitelist of images URL - what should to be converted
     */
    public static $whitelistImgUrls = array();
    
    public static $configFullPath = '';
    
    public static $adminKey = '';
    
    public static $srcsetAttrs = array();
    
    public static $baseUrl = false;
    
    public static $enableLog = false;
    
    public static $cdnDomain = 'cdn.optipic.io';
    
    public static $url = null;
    
    public static $host = null;
    
    /**
     * Constructor
     */
    public function __construct($config = array())
    {
        if (is_array($config) && count($config)>0) {
            self::loadConfig($config);
        }
    }
    
    /**
     * Convert whole HTML-block contains image urls
     */
    public static function convertHtml($content, $detectBaseUrl = true)
    {
        
        if (empty(self::$url)) {
            self::$url = $_SERVER['REQUEST_URI'];
        }
        if (empty(self::$host)) {
            self::$host = $_SERVER['HTTP_HOST'];
        }
        
        $timeStart = microtime(true);
        
        //ini_set('pcre.backtrack_limit', 100000000);
        
        $content = self::removeBomFromUtf($content);
        
        $contentOrig = $content;
        
        // try auto load config from __DIR__.'config.php'
        if (empty(self::$siteId)) {
            self::loadConfig();
        }
        
        if (empty(self::$siteId)) {
            return $contentOrig;
        }
        
        if (!self::isEnabled()) {
            return $contentOrig;
        }
        
        $gziped = false;
        if (self::isGz($content)) {
            if ($contentUngzip = gzdecode($content)) {
                $gziped = true;
                $content = $contentUngzip;
            }
        }
        
        
        $needToConvert = true;
        
        if ($needToConvert && self::htmlHasAmpMarkup($content)) {
            $needToConvert = false;
        }
        
        if ($needToConvert && self::htmlHasXmlMarkup($content)) {
            $needToConvert = false;
        }
        
        if (!$needToConvert) {
            return $contentOrig;
        }
        
        
        if ($detectBaseUrl) {
            self::$baseUrl = self::getBaseUrlFromHtml($content);
            if (self::$baseUrl) {
                self::$baseUrl = parse_url(self::$baseUrl, PHP_URL_PATH);
            }
        }
        
        //if (self::isBinary($content)) {
        //    return $content;
        //}
        
        /*$domains = self::$domains;
        if (!is_array($domains)) {
            $domains = array();
        }
        $domains = array_merge(array(''), $domains);

        $hostsForRegexp = array();
        foreach ($domains as $domain) {
            //$domain = str_replace(".", "\.", $domain);
            if ($domain && stripos($domain, 'http://')!==0 && stripos($domain, 'https://')!==0) {
                $hostsForRegexp[] = 'http://'.$domain;
                $hostsForRegexp[] = 'https://'.$domain;
            }
            else {
                $hostsForRegexp[] = $domain;
            }

        }*/
        //foreach ($hostsForRegexp as $host) {
            
            /*$firstPartsOfUrl = array();
            foreach (self::$whitelistImgUrls as $whiteImgUrl) {
                if (self::substr($whiteImgUrl, -1, 1)=='/') {
                    $whiteImgUrl = self::substr($whiteImgUrl, 0, -1);
                }
                $firstPartsOfUrl[] = preg_quote($host.$whiteImgUrl, '#');
            }
            if (count($firstPartsOfUrl)==0) {
                $firstPartsOfUrl[] = preg_quote($host, '#');
            }
            //var_dump($firstPartsOfUrl);
            //$host = preg_quote($host, '#');
            //var_dump(self::$whitelistImgUrls);

            $host = implode('|', $firstPartsOfUrl);
            var_dump($host);*/
            
            /*$firstPartsOfUrl = array();
            foreach (self::$whitelistImgUrls as $whiteImgUrl) {
                $firstPartsOfUrl[] = preg_quote($whiteImgUrl, '#');
            }
            if (empty($firstPartsOfUrl)) {
                $firstPartsOfUrl = array('/');
            }

            $firstPartOfUrl = implode('|', $firstPartsOfUrl);
            */
            
        //$host = preg_quote($host, '#');
        $host = '';
        
        //$firstPartOfUrl = '/';
        $firstPartOfUrl = '';
        
        // --------------------------------------------
        // <img srcset="">
        // @see https://developer.mozilla.org/ru/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images
        if (!empty(self::$srcsetAttrs)) {
            // srcset|data-srcset|data-wpfc-original-srcset
            $srcSetAttrsRegexp = array();
            foreach (self::$srcsetAttrs as $attr) {
                $srcSetAttrsRegexp[] = preg_quote($attr, '#');
            }
            $srcSetAttrsRegexp = implode('|', $srcSetAttrsRegexp);
            //$content = preg_replace_callback('#<(?P<tag>[^\s]+)(?P<prefix>.*?)\s+(?P<attr>'.$srcSetAttrsRegexp.')=(?P<quote1>"|\')(?P<set>[^"]+?)(?P<quote2>"|\')(?P<suffix>[^>]*?)>#siS', array(__NAMESPACE__ .'\ImgUrlConverter', 'callbackForPregReplaceSrcset'), $content);
            $contentAfterReplace = preg_replace_callback('#<(?P<tag>source|img|picture)(?P<prefix>[^>]*)\s+(?P<attr>'.$srcSetAttrsRegexp.')=(?P<quote1>"|\'|\\\"|\\\')(?P<set>[^"\']+?)(?P<quote2>"|\'|\\\"|\\\')(?P<suffix>[^>]*)>#siS', array(__NAMESPACE__ .'\ImgUrlConverter', 'callbackForPregReplaceSrcset'), $content);
            if (!empty($contentAfterReplace)) {
                $content = $contentAfterReplace;
            }
        }
        // --------------------------------------------
        
        //$regexp = '#("|\'|\()'.$host.'('.$firstPartOfUrl.'[^/"\'\s]{1}[^"\']*\.(png|jpg|jpeg){1}(\?.*?)?)("|\'|\))#siS';
        
        // from 1.10 version
        //$regexp = '#("|\'|\()'.$host.'('.$firstPartOfUrl.'[^"|\'|\)\(]+\.(png|jpg|jpeg){1}(\?.*?)?)("|\'|\))#siS';
        
        $urlBorders = array(
            array('"', '"', '"'),       // "<url>"
            array('\'', '\'', '\''),    // '<url>'
            array('\(', '\)', '\)\('),    // (<url>)
            array('\\\"', '\\\"', '"'), // "<url>" in JSON
            array("\\\'", "\\\'", "'"), // '<url>' in JSON
        );
        
        $cdnDomains = array(
            'cdn.optipic.io',
        );
        
        $cdnDomains[] = self::$cdnDomain;
        $cdnDomains = array_unique($cdnDomains);
        
        $cdnDomainsForRegexp = array();
        foreach ($cdnDomains as $cdnDomain) {
            $cdnDomainsForRegexp[] = '\/\/'.preg_quote($cdnDomain, '#');   // plain html
            $cdnDomainsForRegexp[] = '\\/\\/'.preg_quote($cdnDomain, '#'); // html in json
        }
        $cdnDomainsForRegexp = implode("|", $cdnDomainsForRegexp);
        
        $regexp = array();
        foreach ($urlBorders as $border) {
            $regexp[] = '#('.$border[0].')\s*'.$host.'('.$firstPartOfUrl.'(?!'.$cdnDomainsForRegexp.')[^'.$border[2].']+\.(png|jpg|jpeg){1}(\?[^"\'\s]*?)?)\s*('.$border[1].')#siS';
        }
        //var_dump($regexp);exit;
        
        //$regexp = str_replace('//', '/');
        
        //$content = preg_replace($regexp, '${1}//cdn.optipic.io/site-'.self::$siteId.'${2}${5}', $content);
        $contentAfterReplace = preg_replace_callback($regexp, array(__NAMESPACE__ .'\ImgUrlConverter', 'callbackForPregReplace'), $content);
        if (!empty($contentAfterReplace)) {
            $content = $contentAfterReplace;
        }
            
        //}
        
        //self::$baseUrl = false; // ?
        
        $content = str_replace('<head>', '<head>' . PHP_EOL . self::getPreloadTags(), $content);
        
        if ($gziped) {
            $content = gzencode($content);
            
            // modify Content-Length if it's already sent
            $headersList = self::getResponseHeadersList();
            if (is_array($headersList) && !empty($headersList['Content-Length'])) {
                header('Content-Length: ' . self::strlen($content));
            }
        }
        
        $timeEnd = microtime(true);
        self::log(($timeEnd-$timeStart), 'Conversion finished in (sec.):');
        
        return $content;
    }
    
    
    
    public static function getPreloadTags()
    {
        return '<link href="//'.self::$cdnDomain.'/preload.css" rel="stylesheet">' . PHP_EOL
               . '<link rel="preload" href="//'.self::$cdnDomain.'/preload.png" as="image">';
    }
    
    
    
    public static function trimList($list)
    {
        $trimmed = array();
        foreach ($list as $item) {
            $item = trim(str_replace(array("\r\n", "\n", "\r"), '', $item));
            if (!empty($item)) {
                $trimmed[] = $item;
            }
        }
        return $trimmed;
    }
    
    public static function textToArray($data)
    {
        if (is_array($data)) {
            $array = $data;
        } else {
            $array = explode("\n", $data);
        }
        
        if (!is_array($array)) {
            $array = array();
        }
        $array = self::trimList($array);
        $array = array_unique($array);
        
        
        
        return $array;
    }
    
    /**
     * Load config from file or array
     */
    public static function loadConfig($source = false)
    {
        if ($source===false) {
            $source = dirname(__FILE__) . '/config.php';
        }
        
        if (is_array($source)) {
            self::$siteId = $source['site_id'];
            
            self::$domains = self::textToArray($source['domains']);
            
            self::$exclusionsUrl = self::textToArray($source['exclusions_url']);
            
            self::$whitelistImgUrls = self::textToArray($source['whitelist_img_urls']);
            
            self::$srcsetAttrs = self::textToArray($source['srcset_attrs']);
            
            if (isset($source['admin_key'])) {
                self::$adminKey = $source['admin_key'];
            }
            
            if (isset($source['log'])) {
                if ($source['log']) {
                    self::$enableLog = true;
                }
            }
            
            if (!empty($source['cdn_domain'])) {
                self::$cdnDomain = $source['cdn_domain'];
            }
        } elseif (file_exists($source)) {
            $config = require($source);
            if (is_array($config)) {
                self::$configFullPath = $source;
                self::loadConfig($config);
            }
        }
    }
    
    /**
     * Check if convertation enabled on current URL
     */
    public static function isEnabled()
    {
        //$url = $_SERVER['REQUEST_URI'];
        $url = self::$url;
        if (in_array($url, self::$exclusionsUrl)) {
            return false;
        }
        // check rules with mask
        foreach (self::$exclusionsUrl as $exclUrl) {
            if (self::substr($exclUrl, -1)=='*') {
                $regexp = "#^".self::substr($exclUrl, 0, -1)."#i";
                if (preg_match($regexp, $url)) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * Callback-function for preg_replace() to replace image URLs
     */
    public static function callbackForPregReplace($matches)
    {
        self::log($matches, 'callbackForPregReplace -> $matches');
        $replaceWithoutOptiPic = $matches[0];
        
        $urlOriginal = $matches[2];
        
        $slash = '/';
        // skip images from json (json-encoded)
        if (stripos($replaceWithoutOptiPic, "\\/")!==false) {
            //return $replaceWithoutOptiPic;
            $slash = '\\/';
            //var_dump($urlOriginal);
            $parseUrl = parse_url(json_decode('"'.$urlOriginal.'"'));
            //var_dump($parseUrl);
            $parseUrl['path'] = trim(json_encode($parseUrl['path']), "'\"");
            if (!empty($parseUrl['query'])) {
                $parseUrl['query'] = trim(json_encode($parseUrl['query']), "'\"");
            }
            //var_dump($parseUrl);exit;
        } else {
            $parseUrl = parse_url($urlOriginal);
        }
        
        
        
        
        if (!empty($parseUrl['host'])) {
            if (!in_array($parseUrl['host'], self::$domains)) {
                self::log($urlOriginal, 'callbackForPregReplace -> url original:');
                self::log($replaceWithoutOptiPic, 'callbackForPregReplace -> url with optipic:');
                return $replaceWithoutOptiPic;
            }
        }
        
        $ext = self::strtolower(pathinfo($parseUrl['path'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('png', 'jpg', 'jpeg'))) {
            return $replaceWithoutOptiPic;
        }
        
        if (self::urlHasPhpScript($urlOriginal)) {
            return $replaceWithoutOptiPic;
        }
        
        $urlOriginal = $parseUrl['path'];
        if (!empty($parseUrl['query'])) {
            $urlOriginal .= '?'.$parseUrl['query'];
        }
        $urlOriginal = self::getUrlFromRelative($urlOriginal, self::$baseUrl, $slash);
        
        $replaceWithOptiPic = $matches[1].$slash.$slash.self::$cdnDomain.$slash.'site-'.self::$siteId.$urlOriginal.$matches[5];
        
        self::log($urlOriginal, 'callbackForPregReplace -> url original:');
        self::log($replaceWithOptiPic, 'callbackForPregReplace -> url with optipic:');
        
        if (self::substr($urlOriginal, 0, 7)=='http://') {
            return $replaceWithoutOptiPic;
        }
        if (self::substr($urlOriginal, 0, 8)=='https://') {
            return $replaceWithoutOptiPic;
        }
        if (self::substr($urlOriginal, 0, 2)=='//') {
            return $replaceWithoutOptiPic;
        }
        
        if (empty(self::$whitelistImgUrls)) {
            return $replaceWithOptiPic;
        }
        
        if (in_array($urlOriginal, self::$whitelistImgUrls)) {
            return $replaceWithOptiPic;
        }
        
        foreach (self::$whitelistImgUrls as $whiteUrl) {
            if (strpos($urlOriginal, $whiteUrl)===0) {
                return $replaceWithOptiPic;
            }
        }
        
        return $replaceWithoutOptiPic;
    }
    
    /**
     * Callback-function for preg_replace() to replace "srcset" attributes
     */
    public static function callbackForPregReplaceSrcset($matches)
    {
        $isConverted = false;
        $originalContent = $matches[0];
        
        $listConverted = array();
        
        $list = explode(",", $matches['set']);
        foreach ($list as $item) {
            $source = preg_split("/[\s,]+/siS", trim($item));
            $url = trim($source[0]);
            $size = (isset($source[1]))? trim($source[1]): '';
            $toConvertUrl = "'".$url."'";
            $convertedUrl = self::convertHtml($toConvertUrl, false);
            if ($toConvertUrl!=$convertedUrl) {
                $isConverted = true;
                $listConverted[] = trim(self::substr($convertedUrl, 1, -1).' '.$size);
            }
        }
        
        if ($isConverted) {
            return '<'.$matches['tag'].$matches['prefix'].' '.$matches['attr'].'='.$matches['quote1'].implode(", ", $listConverted).$matches['quote2'].$matches['suffix'].'>';
        } else {
            return $originalContent;
        }
    }
    
    /*public static function isBinary($str)
    {
        return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
    }*/
    
    /**
     * Remove UTF-8 BOM-symbol from text
     */
    public static function removeBomFromUtf($text)
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }
    
    /**
     * Check if gziped data
     */
    public static function isGz($str)
    {
        if (self::strlen($str) < 2) {
            return false;
        }
        return (ord(self::substr($str, 0, 1)) == 0x1f && ord(self::substr($str, 1, 1)) == 0x8b);
    }
    
    public static function getUrlFromRelative($relativeUrl, $baseUrl = false, $slash = '/')
    {
        self::log($relativeUrl, 'getUrlFromRelative -> param orig');
        if (stripos($relativeUrl, '../')!==false) {
            $relativeUrl = self::resolveFilename($relativeUrl, $slash);
        }
        
        if (self::substr($relativeUrl, 0, self::strlen($slash))==$slash) {
            return $relativeUrl;
        }
        /*if (self::substr($relativeUrl, 0, 2)=='\/') { // for json-encoded urls when / --> \/
            return $relativeUrl;
        }*/
        
        if (!$baseUrl) {
            //$baseUrl = pathinfo(self::$url, PATHINFO_DIRNAME);
            $baseUrl = self::getBaseDirOfUrl(self::$url);
        }
        //$baseUrl .= '/';
        
        // CASE filepath ".img.png" (remove first dot)
        if (self::substr($relativeUrl, 0, 1) == '.' && self::substr($relativeUrl, 1, 1) != '.') {
            $relativeUrl = self::substr($relativeUrl, 1);
        }
        // CASE baseUrl "." (remove first dot)
        if (self::strlen($baseUrl)>0 && self::substr($baseUrl, 0, 1) == '.' && self::substr($baseUrl, 1, 1) != '.') {
            $baseUrl = (self::strlen($baseUrl)>1)? "".self::substr($baseUrl, 1): "";
        }
        
        // CASE /catalog + img.png (/catalogimg.png is wrong)
        if (self::substr($baseUrl, -1)!='/' && self::substr($relativeUrl, 0, 1) != '/') {
            $tryUrl = str_replace($slash.$slash, $slash, $baseUrl.$slash.$relativeUrl);
            // Try to /catalog/img.png
            if (file_exists(self::getDocumentDoot().$slash.$tryUrl)) {
                return $tryUrl;
            } else { // Try to /img.png
                $tryUrl = str_replace($slash.$slash, $slash, '/'.$relativeUrl);
                if (file_exists(self::getDocumentDoot().$slash.$tryUrl)) {
                    return $tryUrl;
                }
            }
        }
        
        // double slash to one slash
        $url = str_replace($slash.$slash, $slash, $baseUrl.$relativeUrl);
        return $url;
    }
    
    
    
    public static function resolveFilename($filename, $slash = '/')
    {
        $filename = str_replace($slash.$slash, $slash, $filename);
        $parts = explode($slash, $filename);
        $out = array();
        foreach ($parts as $part) {
            if ($part == '.') {
                continue;
            }
            if ($part == '..') {
                array_pop($out);
                continue;
            }
            $out[] = $part;
        }
        return implode($slash, $out);
    }
    
    
    
    /**
     * Get main base path (dir) from full URL
     *
     * https://domain.com/catalog/catalog.php --> https://domain.com/catalog/
     */
    public static function getBaseDirOfUrl($url)
    {
        $urlParsed = parse_url($url);
        if (empty($urlParsed['path'])) {
            return '/';
        }
        $urlPath = $urlParsed['path'];
        $baseUrl = $urlPath;
        $pathinfo = pathinfo($urlPath);
        if (!empty($pathinfo['extension'])) {
            $baseUrl = $pathinfo['dirname'];
        }
        
        if (stripos($url, $baseUrl.'/')!==false) {
            $baseUrl .= '/';
        }
        
        if (self::substr($baseUrl, -1)!='/') {
            $pathinfo = pathinfo($baseUrl);
            if (!empty($pathinfo['dirname'])) {
                $baseUrl = $pathinfo['dirname'];
            }
        }
        
        return $baseUrl;
    }
    
    public static function getBaseUrlFromHtml($html)
    {
        preg_match('#(?P<tag><base)(?P<prefix>[^>]*)\s+href=(?P<base_url>[^>\s]+)#isS', $html, $matches);
        
        $baseUrl = false;
        if (!empty($matches['base_url'])) {
            $baseUrl = trim($matches['base_url'], '"/');
            $baseUrl = trim($baseUrl, "'");
            $baseUrl = self::getBaseDirOfUrl($baseUrl);
            /*if (self::strlen($baseUrl)>0 && self::substr($baseUrl, -1, 1)!='/') {
                $baseUrl .= '/';
            }*/
        }
        return $baseUrl;
    }
    
    public static function getResponseHeadersList()
    {
        $list = array();
        
        $headersList = headers_list();
        if (is_array($headersList)) {
            foreach ($headersList as $row) {
                list($headerKey, $headerValue) = explode(":", $row);
                $headerKey = trim($headerKey);
                $headerValue = trim($headerValue);
                $list[$headerKey] = $headerValue;
            }
        }
        
        return $list;
    }
    
    
    
    /**
     * Log debug info into file
     */
    public static function log($data, $comment = '')
    {
        if (!self::$enableLog) {
            return;
        }
        
        $date = \DateTime::createFromFormat('U.u', microtime(true));
        if (!$date) {
            $date = new \DateTime();
        }
        $dateFormatted = $date->format("Y-m-d H:i:s u");
        
        $line = "[$dateFormatted] ".self::$url."\n";
        if ($comment) {
            $line .= "# ".$comment."\n";
        }
        $line .= var_export($data, true)."\n";
        file_put_contents(dirname(__FILE__) . '/log.txt', $line, FILE_APPEND);
    }
    
    
    
    public static function getDefaultSettings($settingKey = false)
    {
        $settings = array(
            'srcset_attrs' => array(
                'srcset',
                'data-srcset',
            ),
            'domains' => array(),
        );
        
        if ($currentDomain = self::getCurrentDomain(true)) {
            $settings['domains'] = array(
                $currentDomain,
                'www.'.$currentDomain,
            );
        }
        
        if ($settingKey) {
            return (!empty($settings[$settingKey]))? $settings[$settingKey]: '';
        }
        
        return $settings;
    }
    
    
    public static function getCurrentDomain($trimWww = false)
    {
        if (is_null(self::$host) && !empty($_SERVER['HTTP_HOST'])) {
            self::$host = $_SERVER['HTTP_HOST'];
        }
        
        if (empty(self::$host)) {
            return false;
        }
        
        $currentHost = explode(":", self::$host);
        $currentHost = trim($currentHost[0]);
        if ($trimWww) {
            if (stripos($currentHost, 'www.')===0) {
                $currentHost = self::substr($currentHost, 4);
            }
        }
        
        return $currentHost;
    }
    
    
    public static function strlen($str)
    {
        //return strlen($str);
        
        return \Tools::strlen($str);
    }
    
    
    public static function substr($string, $offset, $length = null)
    {
        //return substr($string, $offset, $length);
        
        if (is_null($length)) {
            $length = false;
        }
        return \Tools::substr($string, $offset, $length);
    }
    
    
    public static function strtolower($str)
    {
        //return strtolower($str);
        
        return \Tools::strtolower($str);
    }
    
    
    public static function htmlHasAmpMarkup($html)
    {
         return (stripos($html, "<html amp")!==false);
    }
    
    
    public static function htmlHasXmlMarkup($html)
    {
         return (stripos($html, "<?xml")!==false);
    }
    
    public static function getDocumentDoot()
    {
         return $_SERVER['DOCUMENT_ROOT'];
    }
    
    
    /**
     * Check if URL has php script logic (no static image)
     * Examples:
     * - /index.php?route=product/image/catalog/payment.png
     * - /manager/?a=system/file/edit&file=assets/template/css/../images/lines.png
     */
    public static function urlHasPhpScript($url)
    {
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        $posQ = stripos($url, '?');
        
        if (!$posQ) {
            $posQ = stripos($url, '&');
            if (!$posQ) {
                return false;
            }
        }
        
        if (!$ext) {
            return true;
        }
        
        $posExt = stripos($url, $ext);
        if ($posQ < $posExt) {
            return true;
        }
        
        return false;
    }
}
