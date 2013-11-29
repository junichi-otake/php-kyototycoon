php-kyototycoon
===============

Kyototycoonをphpから使います。

デフォルトで、lz4 と messagepackを利用していますので、使わない場合は、PHPかJSON
にしてお試しください。

lua play_script対応
get|set bulk対応

ご参考にどうぞ

Junichi Otake. <junichi.otake@gmail.com>

for simply using kyototycoon

usage
-----

    $kt = new Kyototycoon( 'localhost', 1978 );
    $kt->set('keyname', 'value_string' );
    echo $kt->get('keyname');
    // value_string


Kyoto Tycoon
============

http://fallabs.com/kyototycoon/

http://fallabs.com/
