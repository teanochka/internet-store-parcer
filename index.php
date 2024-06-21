<?php

error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . "/phpQuery-onefile.php";

$connect = mysqli_connect('MySQL-8.2', 'root', '', 'parce'); // о нет, у меня украдут мою краденную информацию

if (!$connect) {
    die(mysqli_connect_error());
}

$home = 'https://tuneapp.ru/'; 
$loadMore = 'https://tuneapp.ru/index.php?route=common/home/ajax_featured&page=';

function parser($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$page = 1;
$hasMore = true;
$cardData = array(); // массив товаров
$keys = array(); // все уникальные поля

while ($hasMore) { // проходим по всей главной странице
    $url = $page == 1 ? $home : $loadMore . $page; // ссылка для парса 
    $result = parser($url);
    $pq = phpQuery::newDocument($result);

    $cpData = array(); // cp stands for current page, what did you think?!
    $productCards = $pq->find('.product-card');

    if ($productCards->length === 0) {
        $hasMore = false;
    } else {
        foreach ($productCards as $product) {
            $product = pq($product);
            $details = array(); // массив характеристик товара
            foreach ($product->find('.product-card__chars-row') as $row) {
                $key = pq($row)->find('.product-card__chars-row-key')->text();
                $key = rtrim($key, ": \t\n\r\0\x0B"); //обрезаем текст и меняем пробелы на нижние подчеркивания
                $key = str_replace(' ', '_', $key);
                $value = pq($row)->find('.product-card__chars-row-value')->text();
                $value = mb_substr($value, 0, 255); // да, я решила обрезать описания, эта бд же не на самом деле куда-то пойдет
                $details[$key] = $value;
                if (!in_array($key, $keys)) {
                    $keys[] = $key;
                }
            }
            $cpData[] = $details; // собираем инфу по товарам на текущей странице
        }

        $cardData = array_merge($cardData, $cpData);
        $page++;
    }
}

$sql_drop = "DROP TABLE IF EXISTS товары";
if ($connect->query($sql_drop) === false) {
    echo $connect->error . "<br>";
}

$sql_create = "CREATE TABLE товары (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    " . implode(" VARCHAR(255), ", $keys) . " VARCHAR(255)
)";
if ($connect->query($sql_create) === false) {
    echo $connect->error . "<br>";
}

foreach ($cardData as $card) {
    $insertValues = array_map(function($value) use ($connect) {
        return "'" . mysqli_real_escape_string($connect, $value) . "'";
    }, $card);

    $columns = implode(', ', array_keys($card));
    $values = implode(', ', $insertValues);

    $sql_insert = "INSERT INTO товары ($columns) VALUES ($values)";

    if ($connect->query($sql_insert) === false) {
        echo $connect->error . "<br>";
    }
}

