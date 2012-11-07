<?php

class ParallelCurl {

  protected $aHandlers;

  public function __construct() {

    $this->aHandlers = array();
    $this->rMultiHandler = curl_multi_init();

  }

  public function addUrl($sUrl) {

    $rHandler = curl_init();
    curl_setopt($rHandler, CURLOPT_URL, $sUrl);
    curl_setopt($rHandler, CURLOPT_HEADER, 0);
    curl_setopt($rHandler, CURLOPT_RETURNTRANSFER, 1);
    curl_multi_add_handle($this->rMultiHandler, $rHandler);    
    $this->aHandlers[$sUrl] = $rHandler;
  }

  public function run() {

    $blsRunning = null;

    do {
      $rHandler = curl_multi_exec($this->rMultiHandler, $blsRunning);
    } while ($rHandler === CURLM_CALL_MULTI_PERFORM);

    while ($blsRunning && $rHandler == CURLM_OK) {
      if (curl_multi_select($this->rMultiHandler) != -1) {
        do {
          $rHandler = curl_multi_exec($this->rMultiHandler, $blsRunning);
        } while ($rHandler == CURLM_CALL_MULTI_PERFORM);
      }
    }

    foreach ($this->aHandlers as $url => $handler) {
      $data[$url] = curl_multi_getcontent($handler);
      curl_multi_remove_handle($this->rMultiHandler, $handler);
    }
  
    $this->aHandlers = array();
    return $data;
  }

  public function __destruct() {
    curl_multi_close($this->rMultiHandler);
  }

}

