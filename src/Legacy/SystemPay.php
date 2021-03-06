<?php

namespace Waaz\SystemPayPlugin\Legacy;

/**
 * @author Ibes Mongabure <developpement@studiowaaz.com>
 */
class SystemPay
{
    /**
     * @var string
     */
    private $paymentUrl = 'https://systempay.cyberpluspaiement.com/vads-payment/';

    /**
     * @var array
     */
    private $mandatoryFields = array(
        'action_mode' => null,
        'ctx_mode' => null,
        'page_action' => null,
        'payment_cards' => null,
        'payment_config' => null,
        'site_id' => null,
        'version' => 'V2',
        'redirect_success_message' => null,
        'redirect_error_message' => null,
        'url_return' => null,
    );

    /**
     * @var string
     */
    private $key;

    /**
     * @var bool
     */
    private $useOldSecurity;

    public function __construct($key)
    {
        $this->key = $key;
        $this->mandatoryFields['trans_date'] = gmdate('YmdHis');
    }

    /**
     * @param $fields
     * remove "vads_" prefix and form an array that will looks like :
     * trans_id => x
     * cust_email => xxxxxx@xx.xx
     * @return $this
     */
    public function setFields($fields)
    {
        foreach ($fields as $field => $value)
            if (empty($this->mandatoryFields[$field]) || $field == 'payment_config')
                $this->mandatoryFields[$field] = $value;
        return $this;
    }

    /**
     * @param bool $useOldSecurity
     */
    public function setUseOldSecurity($useOldSecurity)
    {
        $this->useOldSecurity = $useOldSecurity;
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        $this->mandatoryFields['signature'] = $this->getSignature();
        return $this->mandatoryFields;
    }

    /**
     * @return string
     */
    public function getPaymentUrl()
    {
        return $this->paymentUrl;
    }

    /**
     * @param array $fields
     * @return array
     */
    private function setPrefixToFields(array $fields)
    {
        $newTab = array();
        foreach ($fields as $field => $value)
            $newTab[sprintf('vads_%s', $field)] = $value;
        return $newTab;
    }

    /**
     * @param null $fields
     * @return string
     */
    private function getSignature($fields = null)
    {
        if (!$fields)
            $fields = $this->mandatoryFields = $this->setPrefixToFields($this->mandatoryFields);
        ksort($fields);
        $contenu_signature = "";
        foreach ($fields as $field => $value)
                $contenu_signature .= $value."+";
        $contenu_signature .= $this->key;

        if ($this->useOldSecurity) {
            $signature = sha1($contenu_signature);
        } else {
            $signature = base64_encode(hash_hmac('sha256', $contenu_signature, $this->key, true));
        }

        return $signature;
    }

    /**
     * @param array $postdata
     * @return bool
     */
    public function responseHandler($postdata)
    {
      //$postdata = $request->request->all();
      // Check signature
      if (!empty($postdata['signature'])) {
          $signature = $postdata['signature'];
          unset ($postdata['signature']);

          /*
           * Detect if the signature is a SHA-1 or not. If it's the case, we are using the old security algorithm.
           * With the new algorithm SHA-256, the signature is also base64_encode, so the regex will not match.
           */
          $this->setUseOldSecurity((bool) preg_match('/^[0-9a-f]{40}$/i', $signature));

          if ($signature === $this->getSignature($postdata)
            && $postdata['vads_trans_status'] === "AUTHORISED") {
              return true;
          }
      }
      return false;
    }

    public function executeRequest()
    {
        $return = "<html><body><form name=\"redirectForm\" method=\"POST\" action=\"" . $this->paymentUrl . "\">";
          foreach ($this->getResponse() as $key => $value) {
            $return .= "<input type=\"hidden\" name=\"$key\" value=\"" .$value. "\">";
          }


        $return .=
            "<noscript><input type=\"submit\" name=\"Go\" value=\"Click to continue\"/></noscript> </form>" .
            "<script type=\"text/javascript\"> document.redirectForm.submit(); </script>" .
            "</body></html>";

        return $return;
    }
}
