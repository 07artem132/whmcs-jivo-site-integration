<?php

require 'helper.php';

use JivoSiteIntegration\helper as JSI_Helper;

function JivoSiteIntegration_config() {

    $configarray = array(
        "name" => "JivoSite integration",
        "description" => "С помощью этого модуля вы можете использовать (<b class=\"label active\">JivoSite</b>) в вашей установке WHMCS..",
        "version" => "1.0.0",
        "author" => "Service-Voice",
        "fields" => array(
            "widget_id" => array("FriendlyName" => "Идентификатор чата", "Type" => "text", "Size" => "25", "Description" => "Он содержится в коде который вам необходимо установить на сайт. Он содержится в widget_id = 'ХХХХХХ' введите то что в кавычках"),
            "DisplayChatAdmins" => array("FriendlyName" => "Показывать чат администратору ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы администраторам показывался чат. Обычно вы не хотите этого, так как вы должны показывать чат только посетителям и клиентам."),
            "WebHooksEnable" => array("FriendlyName" => "Включить обработку Webhooks ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы оператору передавались данные через  Webhooks <span class=\"label active\">ДАННАЯ РЕАЛИЗАЦИЯ безопасна!</span>" .
                "<br/> URL для Webhooks: http://" . $_SERVER['SERVER_NAME'] . '/index.php?m=JivoSiteIntegration <br/>'
                . '<span class="label closed">ВНИМАНИЕ, если вы не включили Webhooks настройки ниже не имеют силы</span>', "Default" => "yes"),
            "ShowBalanceClient" => array("FriendlyName" => "Показывать оператору баланс ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы оператору передавались данные о балансе клиента.", "Default" => "yes"),
            "ShowGroupClient" => array("FriendlyName" => "Показывать оператору группу клиента ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы оператору передавались данные о группе клиента.", "Default" => "yes"),
            "ShowDiscountGroupClient" => array("FriendlyName" => "Показывать оператору скидку группы клиента ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы оператору передавались данные о скидке группы клиента.", "Default" => "yes"),
            "ShowTicketsClient" => array("FriendlyName" => "Показывать оператору тикет(ы) клиента ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы оператору передавались данные о открытых тикетах клиента.", "Default" => "yes"),
            "ShowTicketsClientCount" => array("FriendlyName" => "Сколько тикетов отображать оператору ?", "Type" => "text", "Size" => "25", "Description" => "Они выводятся в порядке приоритета (больше 1 не рекомендуется)", "Default" => "1"),
            "ShowURLAllTicketsClient" => array("FriendlyName" => "Показывать оператору ссылку на все тикеты клиента ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы оператору показывалась ссылка на все тикеты клиента.", "Default" => "yes"),
            "ChatToTickets" => array("FriendlyName" => "После завершения диалога с оператором конвертировать чат в тикет ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы по завершению диалога с оператором чат конвертировался в тикет.", "Default" => "yes"),
            "SupportDeptID" => array("FriendlyName" => "В каком отделе создавать тикеты ? (для чата)", "Type" => "dropdown", "Options" => implode(",", JSI_Helper::WHMCS_DB_GetAllTicketsDepartment())),
            "offlineMessageToTickets" => array("FriendlyName" => "Конвертировать оффлайн сообщения в тикет ?", "Type" => "yesno", "Description" => "Включите это если вы хотите что бы оффлайн сообщения оставленные в чате конвертировался в тикет.", "Default" => "yes"),
            "offlineMessageSupportDeptID" => array("FriendlyName" => "В каком отделе создавать тикеты (для оффлайн сообщений) ?", "Type" => "dropdown", "Options" => implode(",", JSI_Helper::WHMCS_DB_GetAllTicketsDepartment())),
        )
    );

    return $configarray;
}

function JivoSiteIntegration_activate() {
    return array('status' => 'success', 'description' => 'Модуль успешно деактивирован');
}

function JivoSiteIntegration_output() {
    echo '<iframe src="https://app.jivosite.com/#homepage" align="left" style="width:1000px;position:relative;height:660px;border:none;left:18%;">Ваш браузер не поддерживает плавающие фреймы!</iframe>';
}

function JivoSiteIntegration_clientarea($vars) {
    try {
        $JsonObj = JSI_Helper::WebHooks_GetData($vars['WebHooksEnable']);

        if ($JsonObj->event_name == 'chat_accepted' || $JsonObj->event_name == 'chat_updated') {
            $ClientInfo = JSI_Helper::WHMCS_GetClientInfo($JsonObj->visitor->email, [
                        'ShowTicketsClient' => $vars['ShowTicketsClient'],
                        'ShowTicketsClientCount' => $vars['ShowTicketsClientCount'],
                        'ShowURLAllTicketsClient' => $vars['ShowURLAllTicketsClient'],
                        'ShowDiscountGroupClient' => $vars['ShowDiscountGroupClient'],
                        'ShowGroupClient' => $vars['ShowGroupClient'],
                        'ShowBalanceClient' => $vars['ShowBalanceClient'],
            ]);

            JSI_Helper::WebHooks_Json_SentData(JSI_Helper::WebHooks_BildResponse($ClientInfo, $vars['ShowURLAllTicketsClient']), 200);
            die();
        }

        if ($JsonObj->event_name == 'chat_finished') {
            if ($vars['ChatToTickets'] == 'on')
                JSI_Helper::WebHooks_chatToTickets($JsonObj->visitor->email, $JsonObj->chat->messages, $JsonObj->chat->invitation, $vars['SupportDeptID'], $JsonObj->agents[0]->email);
            JSI_Helper::WebHooks_Json_SentData(null, 200);
        }

        if ($JsonObj->event_name == 'offline_message') {
            if ($vars['offlineMessageToTickets'] == 'on')
                JSI_Helper::WebHooks_offlineMessageToTickets($JsonObj->visitor->email, $JsonObj->message, $vars['offlineMessageSupportDeptID']);
            JSI_Helper::WebHooks_Json_SentData(null, 200);
        }
    } catch (\Exception $e) {
        JSI_Helper::WebHooks_Json_SentData($e->getMessage(), 500);
    }

    die();
}

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}