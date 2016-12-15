<?php

use Illuminate\Database\Capsule\Manager as Capsule;

function JivoSiteIntegration_new_hook_page_add_code($vars) {
    $head_return = '<!-- Begin of Service-Voice JivoSiteIntegration module 1.0.0 -->';

    $jivo_onLoadCallback = '';

    $ModuleSetting = Capsule::table('tbladdonmodules')->select('value')->where('module', 'JivoSiteIntegration')->where('setting', 'DisplayChatAdmins')->orwhere('setting', 'widget_id')->get();

    if ($vars['adminLoggedIn'] && $ModuleSetting[1]->value == "") {
        $head_return .= '<!-- ADMIN NO DISPLAY CHAT -->'
                . '<!-- End of Service-Voice JivoSiteIntegration module -->';

        return $head_return;
    }

    $JivoSiteScript = '<script type=\'text/javascript\'>(function(){function a(){var b=document.createElement("script");b.type="text/javascript";b.async=!0;b.src="//code.jivosite.com/script/widget/' . $ModuleSetting[0]->value . '";var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(b,a)}var c=window;"complete"==document.readyState?a():c.attachEvent?c.attachEvent("onload",a):c.addEventListener("load",a,!1)})();';

    if ($vars['client'] != NULL) {
        $jivo_onLoadCallback = 'function jivo_onLoadCallback(){jivo_api.setContactInfo(' . json_encode(['name' => $vars['clientsdetails']['firstname'] . ' ' . $vars['clientsdetails']['lastname'], 'email' => $vars['clientsdetails']['email'], 'phone' => $vars['clientsdetails']['phonenumber']]) . ');jivo_api.setCustomData([{title:"\u041e\u0447\u0438\u0441\u0442\u043a\u0430 \u0434\u0430\u043d\u043d\u044b\u0445 \u0447\u0435\u0440\u0435\u0437 setCustomData",content:"\u041e\u0447\u0438\u0441\u0442\u043a\u0430 \u0434\u0430\u043d\u043d\u044b\u0445 \u0447\u0435\u0440\u0435\u0437 setCustomData",link:""}])};';
    }

    $head_return .=$JivoSiteScript . $jivo_onLoadCallback . '</script>';
    $head_return .='<!-- End of Service-Voice JivoSiteIntegration module -->';

    return $head_return;
}

add_hook("ClientAreaHeadOutput", 1, "JivoSiteIntegration_new_hook_page_add_code");
