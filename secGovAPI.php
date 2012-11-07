<?php

require_once "parallelCurl.php";

class SecGovAPI {

  const SEARCH_LINK = "http://www.sec.gov/cgi-bin/browse-edgar";
  const BASE_LINK = "http://www.sec.gov/";
  const QUARTERLY_REPORT = '10-Q';
  const YEARLY_REPORT = '10-K';

  const MOST_RECENT_REPORT = 0;
  const ALL_REPORTS = 1;

  public function __construct($sSymbol) {

    $this->oParallelCurl = new ParallelCurl();
    $this->sSymbol = $sSymbol;
    $this->oDoc = new DOMDocument();
    $this->report = array();
  }

  public function getQuaterlyReport($iSearchType = SecGovAPI::MOST_RECENT_REPORT) {

    if ($iSearchType !== SecGovAPI::MOST_RECENT_REPORT && $iSearchType !== SecGovAPI::ALL_REPORTS) {
      return array();
    }

    $aReportLinks = $this->search(SecGovAPI::QUARTERLY_REPORT, $iSearchType);
    var_dump($aReportLinks);
    foreach ($aReportLinks as $link) {
      $this->oParallelCurl->addUrl(SecGovAPI::BASE_LINK."/".$link);
    }
    $aData = $this->oParallelCurl->run();
    foreach ($aData as $link => $xml) {
      $this->oDoc->loadXML($xml);
      $oXPath = new DOMXPath($this->oDoc);

      $oXPath->registerNamespace("xbrli", "http://www.xbrl.org/2003/instance");
      $this->getReportData($oXPath, $link, "dei:EntityCommonStockSharesOutstanding", "CommonStockSharesOutstanding");
      $this->getReportData($oXPath, $link, "us-gaap:NetIncomeLoss", "NetIncomeLoss");


   }
    var_dump($this->report);
  }

  public function getYearlyReport($iSearchType = SecGovAPI::MOST_RECENT_REPORT) {

    if ($iSearchType !== SecGovAPI::MOST_RECENT_REPORT && $iSearchType !== SecGovAPI::ALL_REPORTS) {
      return array();
    }

    $aReportLinks = $this->search(SecGovAPI::YEARLY_REPORT, $iSearchType);
    var_dump($aReportLinks);
    foreach ($aReportLinks as $link) {
      $this->oParallelCurl->addUrl(SecGovAPI::BASE_LINK."/".$link);
    }
    $aData = $this->oParallelCurl->run();
    foreach ($aData as $link => $xml) {
      $this->oDoc->loadXML($xml);
      $oXPath = new DOMXPath($this->oDoc);

      $oXPath->registerNamespace("xbrli", "http://www.xbrl.org/2003/instance");
      $this->getReportData($oXPath, $link, "dei:EntityCommonStockSharesOutstanding", "CommonStockSharesOutstanding");
      $this->getReportData($oXPath, $link, "us-gaap:NetIncomeLoss", "NetIncomeLoss");


    }
    var_dump($this->report);
  }

  public function getReportData($oXPath, $link, $sEntity, $sSaveAs) {

    $oNodelist = $oXPath->query("//xbrli:xbrl/".$sEntity);
    for ($i = 0; $i < $oNodelist->length; $i++) {
      $this->report[$link][$sSaveAs][$i] = $oNodelist->item($i)->nodeValue;
    }

  }

  protected function search($sType, $iSearchType) {

    $aParams = array("company" => "",
                     "match" => "",
                     "CIK" => $this->sSymbol,
                     "filenum" => "",
                     "State" => "",
                     "Country" => "",
                     "SIC" => "",
                     "count" => "40",
                     "owner" => "exclude",
                     "Find" => "Find Companies",
                     "action" => "getcompany",
                     "type" => $sType,
                     "output" => "atom");

    $sUrl = SecGovAPI::SEARCH_LINK . "?". http_build_query($aParams);

    $this->oDoc->load($sUrl);
    $oXPath = new DOMXPath($this->oDoc);
    $oXPath->registerNamespace("atom", "http://www.w3.org/2005/Atom");

    $aLinks = $this->getReportLinks($oXPath, $iSearchType);
    $aReportLinks = array();
    foreach ($aLinks as $link) {
      $this->oParallelCurl->addUrl($link);
    }

    $aHtmls = $this->oParallelCurl->run();
    foreach ($aHtmls as $html) {
      $this->oDoc->loadHTML($html);
      $oXPath = new DOMXPath($this->oDoc);
      $oNodeList = $oXPath->query('//table[@summary="Data Files"]/tr[2]/td[3]/a');
      if ($oNodeList->length === 0) continue;
      $aReportLinks[] = $oNodeList->item(0)->getAttribute("href");
    }
    return $aReportLinks;

  }


  protected function getReportLinks($oXPath, $iSearchType) {

    if ($iSearchType === SecGovAPI::MOST_RECENT_REPORT) {
      $aNodeList = $oXPath->query("//atom:feed/atom:entry[1]/atom:link");
      if ($aNodeList->length === 1) 
        return array($aNodeList->item(0)->getAttribute("href"));
      else 
        return null;
    }
    else if ($iSearchType === SecGovAPI::ALL_REPORTS) {
      $aNodeList = $oXPath->query("//atom:feed/atom:entry/atom:link");
      for ($i = 0; $i < $aNodeList->length; $i++) {
        $aReportLinks[] = $aNodeList->item($i)->getAttribute("href");
      }
      return $aReportLinks;
    }
    else return null;
  }

  protected function getAccessionNumber($oXPath, $iSearchType) {

    if ($iSearchType === SecGovAPI::MOST_RECENT_REPORT) {
      $aNodeList = $oXPath->query("//atom:feed/atom:entry[1]/atom:id");
      if ($aNodeList->length === 1) $sRawAccessNumber = $aNodeList->item(0)->nodeValue;
      else return null;
      return $this->processAccessionNumber($sRawAccessNumber);
    }
    else if ($iSearchType === SecGovAPI::ALL_REPORTS) {
      $aNodeList = $oXPath->query("//atom:feed/atom:entry/atom:id");
      for ($i = 0; $i < $aNodeList->length; $i++) {
        $sRawAccessNumber = $aNodeList->item($i)->nodeValue;
        $aAccessionNumber[] = $this->processAccessionNumber($sRawAccessNumber);
      }
      return $aAccessionNumber; 
    }
    else return null;
  }

  protected function processAccessionNumber($sRawAccessNumber) {
    $aSplitData = preg_split('/accession-number=/', $sRawAccessNumber);
    if (!isset($aSplitData[1])) return null;
    $sAccessionNumber = str_replace('-','', $aSplitData[1]);
    return $sAccessionNumber;
  }

  protected function getReportDate($oXPath, $iSearchType) {

    if ($iSearchType === SecGovAPI::MOST_RECENT_REPORT) {
      $aNodeList = $oXPath->query("//atom:feed/atom:entry[1]/atom:updated");
      if ($aNodeList->length === 1) 
        return $sUpdated = $aNodeList->item(0)->nodeValue;
      else 
        return null;
    }
    else if ($iSearchType === SecGovAPI::ALL_REPORTS) {
      $aNodeList = $oXPath->query("//atom:feed/atom:entry/atom:updated");
      for ($i = 0; $i < $aNodeList->length; $i++) {
        $aUpdated[] = $aNodeList->item($i)->nodeValue;
      }
      return $aUpdated; 
    }
    else return null;
  }
}

