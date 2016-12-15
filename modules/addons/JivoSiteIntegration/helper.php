<?php

namespace JivoSiteIntegration;

use Illuminate\Database\Capsule\Manager as Capsule;

class helper {

    public static function WHMCS_GetClientInfo($email, $settings) {
        if (isset($email) and empty($email))
            return NULL;

        $ClientInfo['Client'] = self::WHMCS_DB_BasicInformationTheClient($email, $settings['ShowBalanceClient']);

        if ($ClientInfo['Client'] == null)
            return null;

        if ($settings['ShowTicketsClient'] == 'on') {
            if ($settings['ShowTicketsClientCount'] < 0)
                throw new \Exception('Некоректное значение ShowTicketsClientCount');

            $ClientInfo['tickets'] = self::WHMCS_DB_TicketsClient($ClientInfo['Client']->id, $settings['ShowTicketsClientCount']);
        }

        if ($settings['ShowGroupClient'] == 'on')
            $ClientInfo['Group'] = self::WHMCS_DB_ClientGroupInfo($ClientInfo['Client']->groupid, $settings['ShowDiscountGroupClient']);

        return $ClientInfo;
    }

    static private function WHMCS_API_CreateTickets($ClientID, $SupportDeptID, $adminlogin, $subject, $message, $priority = 'Low') {
        $APIrequest = [
            'action' => "openticket",
            'clientid' => $ClientID,
            'deptid' => $SupportDeptID,
            'subject' => htmlspecialchars($subject), //
            'message' => htmlspecialchars($message),
            'priority' => $priority,
        ];

        if ($adminlogin != NULL)
            $APIrequest['admin'] = $adminlogin;

        $res = localAPI($APIrequest['action'], $APIrequest, 1);

        if ($res['result'] != 'success')
            throw new \Exception("Error create tickets");

        return $res['id'];
    }

    static private function WHMCS_API_TicketReply($ticketid, $message, $ClientID, $AdminLogin) {
        $APIrequest = [
            'action' => 'AddTicketReply',
            'ticketid' => $ticketid,
            'message' => htmlspecialchars($message),
            'markdown' => TRUE,
        ];

        if (isset($ClientID) && !empty($ClientID))
            $APIrequest['clientid'] = $ClientID;

        if (isset($AdminLogin) && !empty($AdminLogin))
            $APIrequest['adminusername'] = $AdminLogin;

        $res = localAPI($APIrequest['action'], $APIrequest, 1);

        if ($res['result'] != 'success')
            throw new \Exception("Error create tickets");

        return TRUE;
    }

    private static function WHMCS_API_UpdateTicket($ticketid, $status) {
        $APIrequest = [
            'action' => 'updateticket',
            'ticketid' => $ticketid,
            'status' => $status,
        ];

        $res = localAPI($APIrequest['action'], $APIrequest, 1);

        if ($res['result'] != 'success')
            throw new \Exception("Error create tickets");

        return TRUE;
    }

    private static function WHMCS_DB_ClientID($email) {
        try {
            $data = Capsule::table('tblclients')->select(['id'])->where('email', $email)->first();
        } catch (\Exception $e) {
            throw new Exception("Error DB select, info client");
        }

        return $data;
    }

    private static function WHMCS_DB_BasicInformationTheClient($email, $ShowBalanceClient) {
        try {
            $data = Capsule::table('tblclients')->
                    where('email', $email)->
                    first(['id', 'firstname', 'lastname', 'phonenumber', 'email', 'notes', 'credit', 'groupid']);
        } catch (\Exception $e) {
            throw new Exception("Error DB select, info client");
        }

        if ($ShowBalanceClient != 'on')
            unset($data->credit);

        return $data;
    }

    private static function WHMCS_DB_TicketsClient($ClientID, $Limit) {
        try {
            $data = Capsule::table('tbltickets')->
                            select(['id', 'title', 'urgency', 'lastreply'])->
                            where('userid', $ClientID)->
                            Where('status', '!=', 'Closed')->
                            orderByRaw("FIELD(urgency,'High','Medium','Low')")->
                            take($Limit)->get();
        } catch (\Exception $e) {
            throw new Exception("Error DB select, info tickets");
        }

        return $data;
    }

    private static function WHMCS_DB_GetAdminInfo($emailAdmin) {
        try {
            $data = Capsule::table('tbladmins')->select()->where('email', $emailAdmin)->first();
        } catch (\Exception $e) {
            throw new Exception("Error DB select, info admin email to login");
        }

        return $data;
    }

    public static function WHMCS_DB_GetAllTicketsDepartment() {
        try {
            $data = Capsule::table('tblticketdepartments')->select('name', 'id')->get();
        } catch (\Exception $e) {
            throw new Exception("Error DB get info Tickets Department");
        }

        foreach ($data as $line) {
            $depts[] = "" . $line->id . "|" . $line->name;
        }

        return $depts;
    }

    private static function WHMCS_DB_ClientGroupInfo($Groupid, $ShowDiscountGroupClient) {
        if ($Groupid == 0 or $Groupid == NULL)
            return NULL;

        try {
            $data = Capsule::table('tblclientgroups')->where('id', $Groupid)->first(['groupname', 'discountpercent']);
        } catch (\Exception $e) {
            throw new Exception("Error DB select, info group info");
        }

        if ($ShowDiscountGroupClient != 'on')
            unset($data->discountpercent);

        return $data;
    }

    public static function WebHooks_GetData($WebHooksEnable) {
        if ($WebHooksEnable != 'on')
            throw new \Exception("WebHooks disable admin arrea WHMCS");

        $data = file_get_contents('php://input');

        if ($data == null)
            throw new \Exception("php://input is null");

        return self::WebHooks_Json_Decode($data);
    }

    public static function WebHooks_offlineMessageToTickets($email, $message, $SupportDeptID) {
        $Client = self::WHMCS_DB_ClientID($email);
        if ($Client->id == NULL)
            return;

        $ticketid = self::WHMCS_API_CreateTickets($Client->id, $SupportDeptID, Null, "Оффлайн обращение в чат " . date("Y-m-d H:i:s"), $message);

        return TRUE;
    }

    public static function WebHooks_chatToTickets($email, $chat, $FirstMessage, $SupportDeptID, $emailAgent) {
        $Client = self::WHMCS_DB_ClientID($email);
        $Operator = self::WHMCS_DB_GetAdminInfo($emailAgent);

        if ($Client->id == NULL)
            return;

        $ticketid = self::WHMCS_API_CreateTickets($Client->id, $SupportDeptID, $Operator->username, "Чат за " . date("Y-m-d H:i:s", $chat[0]->timestamp), $FirstMessage);

        foreach ($chat as $message) {
            if ($message->type == 'visitor') {
                self::WHMCS_API_TicketReply($ticketid, $message->message, $Client->id, NULL);
            } else {
                self::WHMCS_API_TicketReply($ticketid, $message->message, NULL, $Operator->username);
            }
        }
        self::WHMCS_API_UpdateTicket($ticketid, 'Closed');

        return TRUE;
    }

    public static function WebHooks_Json_SentData($respoce, $code) {
        ($code == 200) ? $data = ["result" => "ok"] : $data = ["result" => "error"];

        !is_array($respoce) ? $data['message'] = $respoce : $data = $data + $respoce;

        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($data);
    }

    private static function WebHooks_Json_Decode($data) {
        $JsonObj = json_decode($data);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $JsonObj;
            case \JSON_ERROR_DEPTH:
                throw new \Exception('Достигнута максимальная глубина стека');
            case \JSON_ERROR_STATE_MISMATCH:
                throw new \Exception('Некорректные разряды или не совпадение режимов');
            case \JSON_ERROR_CTRL_CHAR:
                throw new \Exception('Некорректный управляющий символ');
            case \JSON_ERROR_SYNTAX:
                throw new \Exception('Синтаксическая ошибка, не корректный JSON');
            case \JSON_ERROR_UTF8:
                throw new \Exception('Некорректные символы UTF-8, возможно неверная кодировка');
            default:
                throw new \Exception('Неизвестная ошибка');
        }
    }

    public static function WebHooks_BildResponse($ClientInfo, $ShowURLAllTicketsClient) {
        if ($ClientInfo == NULL) {
            $respoce['custom_data'][]['content'] = 'Клиент не зарегистрирован';
            $respoce['enable_assign'] = FALSE;
            return $respoce;
        }

        $respoce = [
            'contact_info' => [
                'name' => $ClientInfo['Client']->firstname . '' . $ClientInfo['Client']->lastname,
                'email' => $ClientInfo['Client']->email,
                'phone' => $ClientInfo['Client']->phonenumber,
                'description' => $ClientInfo['Client']->notes,
            ],
            'custom_data' => '',
            'enable_assign' => FALSE,
            'crm_link' => 'https://' . $_SERVER['SERVER_NAME'] . '/admin/clientssummary.php?userid=' . $ClientInfo['Client']->id,
        ];

        if (isset($ClientInfo['Client']->credit))
            $respoce['custom_data'][] = [
                'title' => '&nbsp;',
                'content' => "Баланс: " . $ClientInfo['Client']->credit,
                'link' => ''
            ];

        if (isset($ClientInfo['Group']))
            $respoce['custom_data'][] = [
                'title' => 'Группа клиента:',
                'content' => $ClientInfo['Group']->groupname,
                'link' => ''
            ];

        if (isset($ClientInfo['Group']->discountpercent))
            $respoce['custom_data'][] = [
                'title' => 'Скидка от группы:',
                'content' => $ClientInfo['Group']->discountpercent,
                'link' => ''
            ];

        if (isset($ClientInfo['tickets'])) {
            if (count($ClientInfo['tickets']) == 0)
                $respoce['custom_data'][] = array('title' => 'У клиента нет открытых тикетов:', 'content' => '&nbsp;', 'link' => '');
            else
                (count($ClientInfo['tickets']) == 1) ? $respoce['custom_data'][] = array('title' => 'Открытый тикет:', 'content' => '&nbsp;', 'link' => '') : $respoce['custom_data'][] = array('title' => 'Открытые тикеты:', 'content' => '&nbsp;', 'link' => '');

            for ($i = 0; $i < count($ClientInfo['tickets']); $i++) {
                $respoce['custom_data'][] = array(
                    'title' => 'Тема: ' . $ClientInfo['tickets'][$i]->title,
                    'content' => 'Приоритет: ' . $ClientInfo['tickets'][$i]->urgency,
                    'link' => '',
                );
                $respoce['custom_data'][] = array(
                    'title' => '&nbsp;',
                    'content' => 'Открыть тикет',
                    'link' => 'https://' . $_SERVER['SERVER_NAME'] . '/admin/supporttickets.php?action=view&id=' . $ClientInfo['tickets'][$i]->id,
                );
            }
        }
        if ($ShowURLAllTicketsClient == 'on')
            $respoce['custom_data'][] = array(
                'title' => '&nbsp;',
                'content' => 'Все тикеты клиента',
                'link' => 'https://' . $_SERVER['SERVER_NAME'] . '/admin/supporttickets.php?view=any&client=' . $ClientInfo['Client']->id,
            );
        return $respoce;
    }

}
