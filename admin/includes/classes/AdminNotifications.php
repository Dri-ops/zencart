<?php
/**
 * @package admin
 * @copyright Copyright 2003-2018 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Author: zcwilt New in v1.5.6 $
 */

class AdminNotifications
{
    protected $notificationServer = 'http://versionserver.zencart.com/api/notifications';

    public function __construct()
    {
        if (defined('PROJECT_NOTIFICATIONSERVER_URL')) {
            $this->projectNotificationServer = PROJECT_NOTIFICATIONSERVER_URL;
        }
    }

    public function getNotifications($target, $adminId)
    {
        $notificationList = $this->getNotificationInfo();
        $this->pruneSavedState($notificationList);
        $savedState = $this->getSavedState($adminId);
        $result = [];
        foreach ($notificationList as $name => $notification) {
            if ($this->isNotificationAvailable($name, $target, $notification, $savedState)) {
                $result[$name] = $notification;
            }
        }
        return $result;

    }

    protected function getNotificationInfo()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->projectNotificationServer);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 9);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 9);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Plugin Notifications Check');
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        if ($errno > 0) {
            return $this->formatCurlError($errno, $error);
        }
        $result = json_decode($response, true);
        return $result;
    }

    protected function isNotificationAvailable($name, $target, $notification, $savedState)
    {
        if ($notification['target'] !== $target) {
            return false;
        }
        if ($this->isNotificationDismissed($name, $savedState)) {
            return false;
        }
        if (!$this->isNotificationInDate($notification, $this->getCurrentDate())) {
            return false;
        }
        if (!$this->isNotificationInCountry($notification)) {
            return false;
        }
        return true;
    }

    protected function isNotificationDismissed($name, $savedState)
    {
        if (!isset($savedState[$name])) {
            return false;
        }
        return $savedState[$name]['dismissed'];
    }

    protected function isNotificationInDate($notification, $currentDatetime)
    {
        if (!isset($notification['start-date']) && !isset($notification['end-date'])) {
            return true;
        }
        if (isset($notification['start-date']) && $currentDatetime > $notification['start-date']) {
            return false;
        }
        if (isset($notification['end-date']) && $currentDatetime < $notification['end-date']) {
            return false;
        }
        return true;
    }

    protected function isNotificationInCountry($notification)
    {
        if (!isset($notification['countries'])) {
            return true;
        }
        $iso3 = $this->getStoreCountryIso3();
        if (!in_array($iso3, $notification['countries'])) {
            return false;
        }
        return true;
    }

    protected  function getStoreCountryIso3()
    {
        global $db;

        $sql = "SELECT countries_iso_code_3 from " . TABLE_COUNTRIES . " WHERE countries_id = " . STORE_COUNTRY;
        $r = $db->execute($sql);
        $iso3 = $r->fields['countries_iso_code_3'];
        return $iso3;
    }

    protected function getSavedState($adminId)
    {
        global $db;

        $savedState = [];
        $sql = "SELECT * FROM " . TABLE_ADMIN_NOTIFICATIONS . " WHERE admin_id = :adminId:";
        $sql = $db->bindVars($sql, ':adminId:', $adminId, 'integer');
        $results = $db->execute($sql);
        foreach ($results as $result) {
            $savedState[$result['notification_key']]['dismissed'] = $result['dismissed'];
        }
        return $savedState;
    }

    protected function getCurrentDate()
    {
        return new DateTime("now");
    }


    protected function pruneSavedState($notificationList)
    {
        global $db;

        $keys = array_keys($notificationList);
        $keys = implode(',', $keys);
        $sql = "DELETE FROM " . TABLE_ADMIN_NOTIFICATIONS . " WHERE notification_key NOT IN (:keys:)";
        $sql = $db->bindVars($sql, ':keys:', $keys, 'inConstructString');
        $db->execute($sql);

    }
}