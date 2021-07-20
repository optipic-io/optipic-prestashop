(function() {
    
    if(
        typeof(optipicCurrentHost)!='undefined'
        && typeof(optipicSid)!='undefined'
        && typeof(optipicVersion)!='undefined'
    ) {
        var url = 'https://optipic.io/api/cp/stat?domain=' + optipicCurrentHost  + '&sid=' + optipicSid + '&cms=prestashop&stype=cdn&append_to=%23configuration_form&version=' + optipicVersion;

        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = url;    

        document.getElementsByTagName('head')[0].appendChild(script);
    }
    
})();