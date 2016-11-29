<?php

/**
 * Created by PhpStorm.
 * User: wmj
 * Date: 2016/10/14
 * Time: 10:49
 */
class FileNotFoundException extends Exception {
    public function __construct($message = "", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

$keyword = $argv[2];
$dictFilename = $argv[1];
tokenize($keyword, $dictFilename);
function tokenize($keyword, $dictFilename) {


    $dict = readDict($dictFilename);
    echo "\n使用词典:\n";
    print_r($dict);
    $result=[];

    for($i=1;$keyword;$i++){
        $resultSet=[];
        selectWord($keyword, $dict, $resultSet);
        echo "\n\n——————————————————————————————————————————————————————————————————————————————————\n";
        echo "\n第{$i}次选词方案:".resultSetToStr($resultSet)."\n";
        $optimalWordSet=selectOptimalWordSet($resultSet,$dict);
        $word=$optimalWordSet[0];
        echo "\n第{$i}次选中的词组是：",resultSetToStr($optimalWordSet).",选中的词是：".$word."\n\n——————————————————————————————————————————————————————————————————————————————————\n";
        $result[]=$word;
        $keyword=mb_substr($keyword,mb_strlen($word,"UTF8"));

    }
    echo "\n分词结果是:\n";
    echo resultSetToStr($result)."\n";




}


function selectOptimalWordSet($resultSet,$dict) {

    echo "\n最大词组长度选取开始************************************\n";
    $largestTotalLenResultSet = getWordSetWithLargestTotalLen($resultSet);

    echo "\n最大词组长度选取结束************************************\n";
    if (count($largestTotalLenResultSet) === 1) {
        return $largestTotalLenResultSet[0];
    }
    echo "\n最大平均词长选取开始************************************\n";
    $largestAvgLenResultSet = getLargestAvgWordLen($largestTotalLenResultSet);
    echo "\n最大平均词长选取结束************************************\n";
    if (count($largestAvgLenResultSet) === 1) {
        return $largestAvgLenResultSet[0];
    }
    echo "\n最小标准差选取开始************************************\n";
    $largestVarianceResultSet = getMinVariance($largestAvgLenResultSet);
    echo "\n最小标准差选取结束************************************\n";
    if (count($largestVarianceResultSet) === 1) {
        return $largestVarianceResultSet[0];

    }
    echo "\n最大自然对数和选取开始************************************\n";
    $largestLogResultSet = getLargestLogWordLen($largestAvgLenResultSet,$dict);
    echo "\n最大自然对数和选取结束************************************\n";

    return $largestLogResultSet[0];

}

function readKeyword($filename) {
    if (!file_exists($filename)) {
        throw new FileNotFoundException("file not found {$filename}");
    }
    return file_get_contents($filename);
}

function readDict($filename) {
    if (!file_exists($filename)) {
        throw new FileNotFoundException("file not found {$filename}");
    }
    $content = file_get_contents($filename);
    if(empty($content)){
        return [];
    }
    $items = explode("\n", $content);
    //print_r($items);
    $words = [];
    foreach ($items as $item) {
        if(empty($item)){
            continue;
        }
        $subItem = explode("\t", trim($item));

        $words[$subItem[0]] = $subItem[1];

    }

    return $words;


}

function inDict($word, $dict) {

    return isset($dict[$word]);
}

function selectWord($keyword, $dict, &$resultSet, $preSelectedWords = [], $curWordNum = 1, $maxWordNum = 3) {

    $keywordLen = mb_strlen($keyword, 'UTF8');

    $isLetterOrNumeric=false;
    for ($i = 0; $i < $keywordLen; $i++) {

        $wordLen = $i + 1;
        $word = mb_substr($keyword, 0, $wordLen);
        if(preg_match("/^[a-zA-Z0-9]+$/",$word,$matches)){
            $isLetterOrNumeric=true;
            if(preg_match("/[a-zA-Z0-9]/",mb_substr($keyword,$wordLen,1),$matches)&&$wordLen!=$keywordLen){
                continue;
            }

        }
        if ($isLetterOrNumeric||$wordLen == 1||inDict($word, $dict)) {
            $preSelectedWordsCopy = $preSelectedWords;
            $preSelectedWordsCopy[] = $word;
            if($isLetterOrNumeric){
                $isLetterOrNumeric=false;
            }

        }  else {
            continue;
        }

        if ($curWordNum < $maxWordNum && $wordLen != $keywordLen) {
            selectWord(mb_substr($keyword, $wordLen), $dict, $resultSet, $preSelectedWordsCopy, $curWordNum + 1, $maxWordNum);
        } else {
            $resultSet[] = $preSelectedWordsCopy;
        }

    }

}

function resultSetToStr($resultSet){
    $str='';
     if(is_string($resultSet[0])){
         return implode('/x ',$resultSet);
     }
     $i=0;
    foreach ($resultSet as $wordSet){
        $str.=++$i.".".implode('/x ',$wordSet)." ";
    }
    return $str;
}

function getWordSetWithLargestTotalLen($resultSet) {

    $maxLen = -1;
    $largestWordSet = [];
    foreach ($resultSet as $key => $wordSet) {
        echo "\n当前词组：".resultSetToStr($wordSet);
        $len = getWordTotalLen($wordSet);
        if ($len > $maxLen) {
             echo "\t选中,新方案\n";
            $maxLen = $len;
            $largestWordSet = [$wordSet];



        } elseif ($len == $maxLen) {
            echo "\t选中，候选方案\n";
            $largestWordSet[] = $wordSet;
        }else{
            echo "\t丢弃\n";
        }
        echo "\n本次结果：".resultSetToStr($largestWordSet)."\n";



    }

    return $largestWordSet;

}

function getWordTotalLen($wordSet) {
    $totalLen = 0;
    foreach ($wordSet as $word) {
        $totalLen += mb_strlen($word,'UTF8');
    }
    return $totalLen;
}

function getMinVariance($resultSet) {
    $avg=getAvgWordLen($resultSet[0],getWordTotalLen($resultSet[0]));
    $minVariance = 2147483647;
    $minVarianceWords = [];
    foreach ($resultSet as $key => $wordSet) {

        $variance = getVariance($wordSet,$avg);


        echo "\n当前词组：".resultSetToStr($wordSet);
        if ($variance < $minVariance) {
            echo "\t选中，新方案\n";
            $minVariance = $variance;
            $minVarianceWords = [$wordSet];

        } elseif ($variance == $minVariance) {
            echo "\t选中，候选方案\n";
            $minVarianceWords[] = $wordSet;
        }else{
            echo "\t丢弃\n";
        }
        echo "\n本次结果：".resultSetToStr($minVarianceWords)."\n";
    }

    return $minVarianceWords;

}

function getLargestAvgWordLen($resultSet) {
    $totalLen=getWordTotalLen($resultSet[0]);
    $maxLen = -1;
    $maxLargestLogWords = [];
    foreach ($resultSet as $key => $wordSet) {
        echo "\n当前词组：".resultSetToStr($wordSet);
        $len = getAvgWordLen($wordSet,$totalLen);
        if ($len > $maxLen) {
            echo "\t选中，新方案\n";
            $maxLen = $len;
            $maxLargestLogWords = [$wordSet];

        } elseif ($len == $maxLen) {
            echo "\t选中，候选方案\n";
            $maxLargestLogWords[] = $wordSet;
        }else{
            echo "\t丢弃\n";
        }
        echo "\n本次结果：".resultSetToStr($maxLargestLogWords)."\n";
    }


    return $maxLargestLogWords;

}

function getAvgWordLen($wordSet,$totalLen){
    return $totalLen / count($wordSet);
}

function getVariance($wordSet,$avg) {

    $totalVariance = 0;
    foreach ($wordSet as $word)
        $totalVariance += pow((mb_strlen($word) - $avg), 2);
    return $totalVariance/ count($wordSet);
    //return sqrt($totalVariance / count($wordSet));
}




function getFrequency($word,$dict) {
    return isset($dict[$word])?$dict[$word]:1;
}
function getTotalWordLog($wordSet,$dict){
    $total=0;
    foreach ($wordSet as $word){
        $total+=log(getFrequency($word,$dict));
    }
    return $total;
}

function getLargestLogWordLen($resultSet,$dict) {

    $maxLen = -1;
    $maxLargestLogWords = [];
    foreach ($resultSet as $key => $wordSet) {
        echo "\n当前词组：".resultSetToStr($wordSet)."\n";
        $len =  getTotalWordLog($wordSet,$dict);
        if ($len > $maxLen) {
            echo "\t选中，新方案\n";
            $maxLen = $len;
            $maxLargestLogWords = [$wordSet];

        } elseif ($len == $maxLen) {
            echo "\t选中，候选方案\n";
            $maxLargestLogWords[] = $wordSet;
        }else{
            echo "\t丢弃\n";
        }
        echo "\n本次结果：".resultSetToStr($maxLargestLogWords)."\n";
    }


    return $maxLargestLogWords;

}

function mergeResult($resultSet) {

}

