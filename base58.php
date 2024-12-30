<?php

// 入力を取得
$input = readline('入力してください: '); 
$str_len = strlen($input);

// ASCIIコードの10進数の合計を取得
$decimal_sum = 0;
foreach (str_split($input) as $i => $char) {
    $decimal_sum += ord($char) * 2 ** (8 *($str_len- 1 - $i));
}

// 58進数に変換
$remainders = [];
while ($decimal_sum > 0) {
    $remainders[] = $decimal_sum % 58;
    $decimal_sum = intdiv($decimal_sum, 58);
}

$response = '';
foreach (array_reverse($remainders) as $remainder) {
    $response .= '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'[$remainder];
}

echo 'Base58Encoded: ' . $response;
