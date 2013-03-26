<?php
// use Kyoto
class Kyototycoon{
  protected $_host = 'localhost';
  protected $_port = '50311';
  protected $_timeout = 3;
  protected $_baseUrl = '';

  const ENC_PHP = 0;
  const ENC_JSON = 1;
  const ENC_MSGPACK = 2;
  protected $_enctype = 2;

  const SCR_NONE = 0;
  const SCR_TAB = 1;
  protected $_scrtype = 1;
  // parameter string
  const P_KEY   = 'key';
  const P_VALUE = 'value';
  protected $kt ;
  public function __construct(){
    $this->setBase();
  }
  protected function lz4msgpack( $data ){
    return lz4_compress(msgpack_pack($data));
  }
  protected function unlz4msgpack( $data ){
    return msgpack_unpack(lz4_uncompress($data));
  }
  protected function _encode( $value ) {
    switch( $this->_enctype ){
      case self::ENC_PHP:
        return serialize( $value );
        break;
      case self::ENC_JSON:
        return json_encode( $value );
        break;
      default:
        return $this->lz4msgpack($value);
    }
  }
  protected function _decode( $value ) {
    try{
      switch( $this->_enctype ){
        case self::ENC_PHP:
          return unserialize( $value );
          break;
        case self::ENC_JSON:
          if( is_string( $value ) ){
            $data = json_decode( $value, true );
          }else{
            $data = null;
          }
          return (is_array($data))?$data:$value;
          break;
        default: // messagepack
          return $this->unlz4msgpack($value);
      }
    }catch(Exception $e){
      return $value;
    }
  }
  protected function setBase($host=null, $port=null){
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
      if( $k == self::P_VALUE ){
        $params['_'.$k] = $this->_encode($v);
      }else{
        $params['_'.$k] = $v;
      }
    }
    return $this->_httpPost($api, $params);
  }

  public function get_bulk($keys,$db=null,$decode=true){
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
    return $this->_httpPost($api, $params, $decode);
  }
  public function get($key,$db=null){
    $api = $this->_baseUrl;
    if( is_string( $key ) ){
      $api .= 'get';
      $params = array(self::P_KEY=>$key );
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->_httpGet($api, $params);
  }
  public function remove($key,$db=null){
    $api = $this->_baseUrl;
    if( is_string( $key ) ){
      $api .= 'remove';
      $params = array(self::P_KEY=>$key );
    }else{
      return false;
    }
    if($db) $params['DB']=$db;
    return $this->_httpGet($api, $params);
  }
  public function set($key, $value, $db=null){
    $api = $this->_baseUrl.'set';
    $value = $this->_encode( $value );
    $params = array(self::P_KEY=>$key, self::P_VALUE=>$value );
    if($db) $params['DB']=$db;
    return $this->_httpPost($api, $params);
  }
  public function set_bulk($keys, $db=null){
    $api = $this->_baseUrl;
    if( is_array($keys) ){
      $api .= 'set_bulk';
      foreach($keys as $k=>$v){
        $params['_'.$k] = $this->_encode($v);
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
      $tsv .= base64_encode($key)."\t".base64_encode($value)."\n";
      // $tsv .= $key."\t".rawurlencode($value)."\n";
    }
    return $tsv;
  }
  protected $mapload = false;
  protected function mapload($data, $colenc, $decode){
    $rdata = array();
    if( preg_match('/lastid([0-9]*)[^0-9](.+)/', $data, $m1 )){
      $rdata['lastid'] = $m1[1];
      if(preg_match('/value(.+)/', $m1[2], $m2 )){
        $rdata[self::P_VALUE] = $this->_decode( $m2[1] );
      }
    }
    return $rdata;
  }
  protected function tsv2ary( $response, $colenc='U', $decode=false ){
    if($response){
      $data = array();
      $list = explode("\n",$response);
      foreach($list as $item){
        $kv = explode("\t", $item);
        if(count($kv)==2){
          if( $kv[0]=='ERROR' ){
            $data['ERROR'] = $kv[1];
          }else{
            $key = preg_replace('/^_/','',$kv[0]);
            switch( $colenc ){
              case 'U':
                if( $this->mapload ){
                  $data[rawurldecode( $key )] = $this->mapload( rawurldecode($kv[1]), $colenc, $decode );
                }else{
                  $data[rawurldecode( $key ) ]= rawurldecode($kv[1]);
                }
                break;
              case 'B':
                $key = base64_decode( $key );
                $key = preg_replace('/^_/','',$key);
                if( $this->mapload ){
                  $data[$key] = $this->mapload( base64_decode($kv[1]), $colenc, $decode );
                }else{
                  $data[ $key ]= base64_decode($kv[1]);
                }
                break;
              default:
                $data[ $key  ]= $kv[1];
                break;
            }
            if( self::P_VALUE==$key || $decode ){
              $data[ $key ] = $this->_decode( $data[ $key ] );
            }
          }
        }
      }
      return $data;
    }
    return false;
  }

  protected function _httpGet($api, $data){
    $api .='?';
    $api .= http_build_query($data);
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION ,array($this, 'checkHeader' ));
    $this->header='';
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_timeout );
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/tab-separated-values; colenc=U","Content-length: ".strlen($data)));
    $response = curl_exec($ch);
    curl_close($ch);
    if($response){
      $colenc = 'Q';
      if( preg_match('/colenc=(U|B)/',$this->header,$m )){
        $colenc=$m[1];
      }
      return $this->tsv2ary( $response, $colenc );
    }
    return false;
  }
  protected $header;
  protected function checkHeader( $ch, $header ){
    $this->header .= $header;
    return strlen($header);
  }
  protected function _httpPost($api,$data, $decode=false ){
    $domain = '';
    $port = 80;
    $uri = '/';
    if( preg_match('/https*:\/\/([^:]+):([0-9]+)(\/.+)/', $api, $m )){
      $domain = $m[1];
      $port = $m[2];
      $uri = $m[3];
    }
    $fp = fsockopen( $domain , $port );
    if (!$fp) {
      return false;
    } else {
      $data = $this->ary2tsv($data);
      $request = "POST ".$uri." HTTP/1.0\r\n";
      $request .= "Content-Type: text/tab-separated-values; colenc=B\r\n";
      $request .= "Content-length: ".strlen($data)."\r\n";
      $request .= "\r\n";
      $request .= $data;
      fwrite($fp, $request);
      stream_set_timeout($fp, 2);
      $res = '';
      while (!feof($fp)) {
        $res .= fgets($fp, 128);
      }
      $info = stream_get_meta_data($fp);
      fclose($fp);
      if ($info['timed_out']) {
        return false;
        // echo 'Connection timed out!';
      } else {
        if($res){
          list( $header, $body ) = explode("\r\n\r\n", $res);
          if( preg_match('/HTTP\/1.1 ([0-9]+)/', $header, $m )){
            $code = $m[1];
            if( $code == 200 ){
              if( $body ){
                $colenc = 'Q';
                if( preg_match('/colenc=(U|B)/',$header,$m )){
                  $colenc=$m[1];
                }
                return $this->tsv2ary( $body, $colenc, $decode );
              }
              return true;
            }
          }
        }
      }
    }
    return false;
  }
  public function checkSystem(){
    $n = 'check_system';
    $ret = 0; // error
    if( $this->set( $n, time() ) ){
      $ret += 1;
    }
    if( $this->get( $n ) ){
      $ret += 2;
    }
    return $ret;
  }
}
