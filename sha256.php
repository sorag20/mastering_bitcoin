<?php
// 入力を取得
$input = readline('入力してください: ');

// 入力のビット数を取得
$input_bit_count = strlen($input) * 8;

// inputをバイナリに変換
$binary_input = '';
for ($i = 0; $i < strlen($input); $i++) {
    $binary_input .= str_pad(base_convert(ord($input[$i]), 10, 2), 8, '0', STR_PAD_LEFT);
}

// Pre-processing
$pre_processing_512_bits = preProcessing($binary_input, $input_bit_count);

// Chunk loop
$w = chunkLoop($pre_processing_512_bits);

// Compression loop
$response = compressionLoop($w);

// 16進数に変換
// base_convert()の上限はCPU依存の32bitまたは64bitであり、128bitだとオーバーフローするため
// 分割して変換する
$hexadecimal_response = binaryToHex($response);

echo 'SHA-256: ' . $hexadecimal_response . "\n";




function preProcessing(string $input, int $input_bit_count) {
    // 末尾に1を追加
    $input_plus_1 = $input . '1';
    $current_bit_count = strlen($input_plus_1);
    // 末尾を64ビット残して0を追加し448ビットにする
    $input_448_bits = $input_plus_1 . str_repeat('0', (512 - $current_bit_count - 64));
    // 末尾に入力のビット数を追加し512ビットにする
    $response_512_bits = $input_448_bits . str_pad(base_convert($input_bit_count, 10, 2), 64, '0', STR_PAD_LEFT);

    return $response_512_bits;
}

function chunkLoop(string $pre_processing_512_bits) {
    $w = [];
    // 32ビットずつに分割
    for ($i = 0; $i < 16; $i++) {
        $w[$i] = substr($pre_processing_512_bits, $i * 32, 32);
    }
    for ($i = 16; $i < 64; $i++) {
        $s0 = rightRotate($w[$i - 15], 7) ^ rightRotate($w[$i - 15], 18) ^ rightShift($w[$i - 15], 3);
        $s1 = rightRotate($w[$i - 2], 17) ^ rightRotate($w[$i - 2], 19) ^ rightShift($w[$i - 2], 10);

        $w[$i] = addOperation($w[$i - 16], $s0, $w[$i - 7], $s1);
    }

    return $w;
}

function compressionLoop(array $w): string
{
    // 初めの素数2, 3, 5, 7, 11, 13, 17, 19の平方根の少数部の最初の32ビットを用意
    $h0 = '01101010000010011110011001100111';
    $h1 = '10111011011001111010111010000101';
    $h2 = '00111100011011101111001101110010';
    $h3 = '10100101010011111111010100111010';
    $h4 = '01010001000011100101001001111111';
    $h5 = '10011011000001010110100010001100';
    $h6 = '00011111100000111101100110101011';
    $h7 = '01011011111000001100110100011001';

    // 初めの素数64個の立方根の整数部の最初の32ビットを用意
    $k_hexadecimal_arrays = [
        0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
        0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
        0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
        0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
        0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
        0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
        0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
        0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
    ];
    $k_binary_arrays = [];
    foreach ($k_hexadecimal_arrays as $k_hexadecimal) {
        $k_binary_arrays[] = str_pad(base_convert($k_hexadecimal, 10, 2), 32, '0', STR_PAD_LEFT);
    }

    // 変数a~hをh0~h7で初期化し用意
    // 64回のループの中で、最終的なハッシュ値で用いる8つの変数を更新する
    $a = $h0;
    $b = $h1;
    $c = $h2;
    $d = $h3;
    $e = $h4;
    $f = $h5;
    $g = $h6;
    $h = $h7;

    for ($i = 0; $i < 64; $i++) {
        $S1 = rightRotate($e, 6) ^ rightRotate($e, 11) ^ rightRotate($e, 25);
        $ch = ($e & $f) ^ ((~$e) & $g);
        $temp1 = addOperation($h, $S1, $ch, $k_binary_arrays[$i], $w[$i]);

        $S0 = rightRotate($a, 2) ^ rightRotate($a, 13) ^ rightRotate($a, 22);
        $maj = ($a & $b) ^ ($a & $c) ^ ($b & $c);
        $temp2 = addOperation($S0, $maj);

        $h = $g;
        $g = $f;
        $f = $e;
        $e = addOperation($d, $temp1);
        $d = $c;
        $c = $b;
        $b = $a;
        $a = addOperation($temp1, $temp2);
    }

    $h0 = addOperation($h0, $a);
    $h1 = addOperation($h1, $b);
    $h2 = addOperation($h2, $c);
    $h3 = addOperation($h3, $d);
    $h4 = addOperation($h4, $e);
    $h5 = addOperation($h5, $f);
    $h6 = addOperation($h6, $g);
    $h7 = addOperation($h7, $h);

    $response = $h0 . $h1 . $h2 . $h3 . $h4 . $h5 . $h6 . $h7;

    return $response;
}

function rightRotate($input, $shift)
{
    return substr($input, -$shift) . substr($input, 0, -$shift);
}

function rightShift($input, $shift)
{
    return str_repeat('0', $shift) . substr($input, 0, -$shift);
}

/*
* ビット加算（32ビットまでを用いる)
*/
function addOperation(...$inputs)
{
    $sum = 0;
    foreach ($inputs as $input) {
        $sum += bindec(str_pad($input, 32, '0', STR_PAD_LEFT));
    }
    $sum &= 0xFFFFFFFF; // 11111111111111111111111111111111

    return str_pad(decbin($sum), 32, '0', STR_PAD_LEFT);
}

function binaryToHex(string $binary): string {
    $hex = '';
    for ($i = 0; $i < strlen($binary); $i += 4) {
        $chunk = substr($binary, $i, 4);
        $hex .= base_convert($chunk, 2, 16);
    }
    return $hex;
}
?>
