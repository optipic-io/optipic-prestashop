/**
* 2007-2022 OptiPic
*
* NOTICE OF LICENSE
*
* PrestaShop module to integrate with OptiPic.io service to optimize site images.
*
*  @author    OptiPic.io <info@optipic.io>
*  @copyright 2007-2022 OptiPic
*  @license   http://www.opensource.org/licenses/mit-license.html  MIT License
*/
(function() {
    
    if(
        typeof(optipicCurrentHost)!='undefined'
        && typeof(optipicSid)!='undefined'
        && typeof(optipicVersion)!='undefined'
    ) {
        var url = 'https://optipic.io/api/cp/stat?domain=' + optipicCurrentHost  + '&sid=' + optipicSid + '&cms=prestashop&stype=cdn&append_to=%23configuration_form&version=' + optipicVersion + '&source=' + optipicSource;

        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = url;    

        document.getElementsByTagName('head')[0].appendChild(script);
    }
    
})();