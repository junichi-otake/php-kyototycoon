<?php
class Kyototycoon{
  protected $host = 'localhost';
  protected $port = '1978';
  protected $timeout = 3;
  protected $baseUrl = '';

  protected $kt ;
  public function __construct($host=null,$port=null){
    $this->setBaseUrl($host,$port);
  }
  public function setBaseUrl($host,$port){
    if($host) $this->host = $host;
    if($port) $this->port = $port;
    $this->baseUrl = 'http://'.$this->host.':'.$this->port.'/rpc/';
  }
  public function play_script($name, $inmap){
    $api = $this->baseUrl.'play_script';
    $params = array(
    'name'=>$name
    );
    foreach($inmap as $k=>$v){
      $params['_'.$k] = $v;
    }
    return $this->post($api, $params);
  }
  public function remove_bulk($keys,$db=null){
    $api = $this->baseUrl;
    if( is_array($keys) ){
      $api .= 'remove_bulk';
      foreach($keys as $k=>$v){
        $params['_'.$k] = '';
      }
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->post($api, $params);
  }
  public function set_bulk($keys,$db=null){
    $api = $this->baseUrl;
    if( is_array($keys) ){
      $api .= 'set_bulk';
      foreach($keys as $k=>$v){
        $params['_'.$k] = $v;
      }
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->post($api, $params);
  }
  public function get_bulk($keys,$db=null){
    $api = $this->baseUrl;
    if( is_array($keys) ){
      $api .= 'get_bulk';
      foreach($keys as $k){
        $params['_'.$k] = ''; 
      }
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->post($api, $params);
  }
  public function remove($key,$db=null){
    $api = $this->baseUrl;
    if( is_string( $key ) ){
      $api .= 'remove';
      $params = array('key'=>$key );
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->post($api, $params);
  }
  public function get($key,$db=null){
    $api = $this->baseUrl;
    if( is_string( $key ) ){
      $api .= 'get';
      $params = array('key'=>$key );
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->post($api, $params);
  }
  public function set($key, $value, $db=null){
    $api = $this->baseUrl.'set';
    $params = array('key'=>$key, 'value'=>$value );
    if($db) $params['DB']=$db;
    return $this->post($api, $params);
  }
  protected function ary2tsv( $params ){
    $tsv = "";
    foreach($params as $key=>$value){
      $tsv .= $key."\t".rawurlencode($value)."\n";
    }
    return $tsv;
  }
  protected function tsv2ary( $response ){
    if($response){
      $data = array();
      $list = explode("\n",$response);
      foreach($list as $item){
        $kv = explode("\t", $item);
        if(count($kv)==2){
          $data[$kv[0]]=rawurldecode($kv[1]);
        }
      }
      return $data;
    }
    return false;
  }
  protected function post($api, $data){
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/tab-separated-values; colenc=U"));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->ary2tsv($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout );
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
    $response = curl_exec($ch);
    curl_close($ch);
    if($response){
      return $this->tsv2ary( $response );
    }
    return false;
  }
}
