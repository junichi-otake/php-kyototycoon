<?php
// use Kyoto
class Kyototycoon{
  protected $_host = 'localhost';
  protected $_port = '50311';
  protected $_timeout = 3;
  protected $_baseUrl = '';

  protected $kt ;
  public function __construct(){
    $this->_setBase();
  }
  protected function _setBase($host=null, $port=null){
    if( $host ) $this->_host = $host;
    if( $port ) $this->_port = $port;
    $this->_baseUrl = 'http://'.$this->_host.':'.$this->_port.'/rpc/';
  }
  public function play_script($name, $inmap){
    $api = $this->_baseUrl.'play_script';
    $params = array(
    'name'=>$name
    );
    foreach($inmap as $k=>$v){
      $params['_'.$k] = $v;
    }
    return $this->_httpPost($api, $params);
  }
  public function get_bulk($keys,$db=null){
    $api = $this->_baseUrl;
    if( is_array($keys) ){
      $api .= 'get_bulk';
      foreach($keys as $k){
        $params['_'.$k] = '';
      }
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->_httpPost($api, $params);
  }
  public function get($key,$db=null){
    $api = $this->_baseUrl;
    if( is_string( $key ) ){
      $api .= 'get';
      $params = array('key'=>$key );
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->_httpGet($api, $params);
  }
  public function set($key, $value, $db=null){
    $api = $this->_baseUrl.'set';
    if( !is_string($value) ){
      $value = serialize( $value );
    }
    $params = array('key'=>$key, 'value'=>$value );
    if($db) $params['DB']=$db;
    return $this->_httpPost($api, $params);
  }
  public function set_bulk($keys, $db=null){
    $api = $this->_baseUrl;
    if( is_array($keys) ){
      $api .= 'set_bulk';
      foreach($keys as $k=>$v){
        if( is_string($v )){
          $params['_'.$k] = $v;
        }else{
          $params['_'.$k] = serialize($v);
        }
      }
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->_httpPost($api, $params);
  }
  protected function ary2tsv( $params ){
    $tsv = "";
    foreach($params as $key=>$value){
      $tsv .= $key."\t".rawurlencode($value)."\n";
    }
    return $tsv;
  }
  protected function tsv2ary( $response, $colenc='U' ){
    if($response){
      $data = array();
      $list = explode("\n",$response);
      foreach($list as $item){
        $kv = explode("\t", $item);
        if(count($kv)==2){
          switch( $colenc ){
            case 'U':
              $data[rawurldecode( $kv[0] ) ]=rawurldecode(rawurldecode($kv[1]));
              break;
            case 'B':
              $data[base64_decode( $kv[0] ) ]=rawurldecode(base64_decode($kv[1]));
              break;
            default:
              $data[ $kv[0]  ]=rawurldecode($kv[1]);
              break;
          }
        }
      }
      return $data;
    }
    return false;
  }

  protected function _httpGet($api, $data){
    $api .='?';
    foreach( $data as $k=>$v){
      $api .= $k.'='.rawurlencode($v).'&';
    }
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_timeout );
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
    $response = curl_exec($ch);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if($response){
      $colenc = 'Q';
      if( preg_match('/colenc=(U|B)/',$ct,$m )){
        $colenc=$m[1];
      }
      return $this->_tsv2ary( $response, $colenc );
    }
    return false;
  }
  protected function _httpPost($api, $data){
    $start = microtime( true );
    $ch = curl_init($api);

    curl_setopt($ch, CURLOPT_POST, 1);
    $data = $this->_ary2tsv($data);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_timeout );
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/tab-separated-values; colenc=U","Content-length: ".strlen($data)));
    $response = curl_exec($ch);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if($response){
      $colenc = 'Q';
      if( preg_match('/colenc=(U|B)/',$ct,$m )){
        $colenc=$m[1];
      }
      return $this->_tsv2ary( $response, $colenc );
    }
    return false;
  }
}
