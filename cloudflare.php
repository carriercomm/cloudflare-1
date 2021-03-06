<?php

/*
* Created 07-15-2014
* CloudFlare DNS Updated by Nenad Milosavljevic lazarevac@gmail.com
* This script is executed by cron and it detect change of public IP
* If IP is changed it contact CloudFlare via API and update DNS records
* Ensure that you have curl installed ( sudo apt-get install php5-curl )
* API docs: https://www.cloudflare.com/docs/client-api.html
*/

$cf = new cloudFlare();
$result = $cf->setDNSRecords();
echo $result != '' ? "Updated: {$result}" : "Nothing updated";

class cloudflare
{
    private $cf_url        = 'https://www.cloudflare.com/api_json.html';
    private $cf_user       = 'email@gmail.com';
    private $cf_api_key    = '123456';
    private $domain        = 'domain.info';
    private $service_mode  = 1;
    private $ttl           = 1;
    private $cf_dns_id     = array('*.domain.info', 'pi.domain.info');
    private $my_current_ip = '';

    public function __construct()
    {
        $this->my_current_ip = $this->curl('http://myip.dnsomatic.com/');
    }

    // get dns records and update them if needed
    public function setDNSRecords()
    {

        // first get list of dns records and check is update needed
        $dns_records = $this->getDNSRecords();

        $updated = '';
        foreach ($dns_records as $k => $v) {
            if (isset($v['content']) && $v['content'] != $this->my_current_ip) {
                //dns ip is different than current ip, update needed...
                if ($this->updateDNS($v)) {
                    $updated .= "{$v['name']}, ";
                }
            }
        }

        $updated = trim($updated, ", ");

        return $updated;
    }

    // update single DNS record
    private function updateDNS($data = array())
    {
        $params                 = array();
        $params['a']            = 'rec_edit';
        $params['tkn']          = $this->cf_api_key;
        $params['id']           = $data['rec_id'];
        $params['email']        = $this->cf_user;
        $params['z']            = $this->domain;
        $params['type']         = 'A';
        $params['name']         = $data['name'];
        $params['content']      = $this->my_current_ip;
        $params['service_mode'] = $this->service_mode;
        $params['ttl']          = $this->ttl;

        $res = $this->curl($this->cf_url, $params);
        $res = json_decode($res, true);

        return isset($res['result']) && $res['result'] == 'success' ? true : false;
    }

    // return ID's of DNS records specified in $this->cf_dns_id
    private function getDNSRecords()
    {
        $params          = array();
        $params['a']     = 'rec_load_all';
        $params['tkn']   = $this->cf_api_key;
        $params['email'] = $this->cf_user;
        $params['z']     = $this->domain;

        $data = $this->curl($this->cf_url, $params);
        $data = json_decode($data, true);

        if (!isset($data['response']['recs']['objs'])) {
            return false;
        }

        $data = $data['response']['recs']['objs'];
        $result = array();

        foreach ($data as $k => $v) {
            if (!in_array($v['name'], $this->cf_dns_id)) {
                continue;
            }

            $result[] = $v;
        }

        return $result;
    }

    // fetch remote data via curl
    private function curl($url = '', $params = array())
    {
        if ($url == '') {
            return false;
        }

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_FOLLOWLOCATION => true,
        ));

        $data = curl_exec($ch);

        curl_close($ch);

        return $data;
    }
}
