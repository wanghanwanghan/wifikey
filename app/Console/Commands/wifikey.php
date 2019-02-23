<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use QL\QueryList;

class wifikey extends Command
{
    static $InsertContent=null;

    static $InsertPic=[];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wifikey:querylist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'QueryList爬数据';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //先从公司数据库爬取文章内容
        $sql="select artid,subject,url,pagenum,pubdate from iiss_article where topid = 33 and seodescription like '%wifi%' order by artid desc limit 50";

        $res=DB::connection('cis')->select($sql);

        //根据文章url判断是不是爬取过了
        $rules=[
            'content'=>['.zhengwen p','text'],
            'pic'=>['.zhengwen p img','src'],
        ];

        $ql=QueryList::rules($rules);

        foreach ($res as $one)
        {
            //================================================================================================================
            //先检查取没取过
            //每一篇取到的文章
            if ($one->url=='' || preg_match('/chinaiis/',$one->url)) continue;

            $md5url=md5($one->url);

            //返回null说明数据库里没有
            $check=DB::connection('aliyun')->table('history')->where(['md5url'=>$md5url])->first();

            //================================================================================================================
            //如果存在就下一个
            if ($check) continue;

            //================================================================================================================
            //如果没去过，下面开始取得
            //$one->pagenum是一共多少页需要循环的
            for ($i=1;$i<=$one->pagenum;$i++)
            {
                //一篇一篇的取
                //不存在就取内容
                if ($i===1)
                {
                    $url=$one->url;
                }else
                {
                    //.最后一次出现的位置
                    $index=strrpos($one->url,'.');

                    $url=$this->mysplit($one->url,[$index],$i-1);
                }

                //======
                //=    =
                //======
                $data=$ql->get($url)->queryData();

                Log::info($url);

                //循环套的太多，新建一个函数处理
                if ($this->getcontent($data))
                {
                    Log::info('抓取成功：'.$one->subject.'的第'.$i.'页，总共'.$one->pagenum.'页面');
                }else
                {
                    Log::warning('抓取失败：'.$one->subject.'的第'.$i.'页');
                    continue;
                }
            }

            //循环出来以后，等于已经有了一篇文章了，文章入库
            //$one是每篇文章obj，用于取得标题和发布时间
            if ($this->storecontent($one))
            {
                Log::info('入库成功');
            }else
            {
                Log::warning('入库失败：');
                Log::warning('标题：'.$one->subject);
                Log::warning('地址：'.$one->url);
                continue;
            }
        }
    }

    //处理querylist爬取到的内容
    public function getcontent($data)
    {
        //每篇文章trim并且加收尾p标签
        foreach ($data as $row)
        {
            if (isset($row['content']))
            {
                $p=$this->Sbc2Dbc($row['content']);

                if ($p!='')
                {
                    if (!preg_match('/function/',$p)) self::$InsertContent.='<p>'.$p.'</p>';
                }
            }

            if (isset($row['pic']))
            {
                if (count(self::$InsertPic)>=9)
                {

                }else
                {
                    $pic=trim($row['pic']);

                    self::$InsertContent.='<p>'.$pic.'</p>';
                    self::$InsertPic[]=$pic;
                }
            }
        }

        return true;
    }

    //数据入库
    public function storecontent($one)
    {
        $data=[
            'subject'=>$one->subject,
            'pagenum'=>$one->pagenum,
            'url'=>$one->url,
            'md5url'=>md5($one->url),
            'content'=>self::$InsertContent,
            'pubdate'=>$one->pubdate,
            'artid'=>$one->artid,
            'pic1'=>isset(self::$InsertPic[0]) ? self::$InsertPic[0] : null,
            'pic2'=>isset(self::$InsertPic[1]) ? self::$InsertPic[1] : null,
            'pic3'=>isset(self::$InsertPic[2]) ? self::$InsertPic[2] : null,
            'pic4'=>isset(self::$InsertPic[3]) ? self::$InsertPic[3] : null,
            'pic5'=>isset(self::$InsertPic[4]) ? self::$InsertPic[4] : null,
            'pic6'=>isset(self::$InsertPic[5]) ? self::$InsertPic[5] : null,
            'pic7'=>isset(self::$InsertPic[6]) ? self::$InsertPic[6] : null,
            'pic8'=>isset(self::$InsertPic[7]) ? self::$InsertPic[7] : null,
            'pic9'=>isset(self::$InsertPic[8]) ? self::$InsertPic[8] : null,
        ];

        try
        {
            DB::connection('aliyun')->table('history')->insert($data);

        }catch (\Exception $e)
        {
            return false;
        }

        self::$InsertContent=null;
        self::$InsertPic=[];

        return true;
    }

    //在一个字符串中的第几位加什么
    public function mysplit($str,array $index,$num)
    {
        $arr=str_split($str);

        $res='';

        foreach ($arr as $key=>$char)
        {
            if (in_array($key,$index))
            {
                $res.='_'.$num.$char;
            }else
            {
                $res.=$char;
            }
        }

        return $res;
    }

    //全角转半角
    public function Sbc2Dbc($str)
    {
        $arr = array(
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E', 'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O', 'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y', 'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
            'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i', 'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
            'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's', 'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
            'ｙ' => 'y', 'ｚ' => 'z',
            '（' => '(', '）' => ')', '〔' => '(', '〕' => ')', '【' => '[', '】' => ']', '〖' => '[', '〗' => ']', '“' => '"', '”' => '"',
            '‘' => '\'', '’' => '\'', '｛' => '{', '｝' => '}', '《' => '<', '》' => '>', '％' => '%', '＋' => '+', '—' => '-', '－' => '-',
            '～' => '~', '：' => ':', '。' => '.', '、' => ',', '，' => ',', '、' => ',', '；' => ';', '？' => '?', '！' => '!', '…' => '-',
            '‖' => '|', '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"', '　' => ' ', '×' => '*', '￣' => '~', '．' => '.', '＊' => '*',
            '＆' => '&', '＜' => '<', '＞' => '>', '＄' => '$', '＠' => '@', '＾' => '^', '＿' => '_', '＂' => '"', '￥' => '$', '＝' => '=',
            '＼' => '\\', '／' => '/','“'=>'"'
        );

        return strtr($str,$arr);
    }

    public function tableAttr()
    {
        /*
        Schema::connection('aliyun')->create('history', function (Blueprint $table) {
            $table->increments('id')->comment('自增');
            $table->string('subject')->comment('标题');
            $table->string('pagenum')->comment('页数');
            $table->string('url')->comment('地址');
            $table->string('md5url',50)->comment('md5地址')->index();
            $table->text('content')->comment('内容');
            $table->integer('pubdate')->unsigned()->comment('发布时间');
            $table->integer('sourceid')->unsigned()->comment('在cis库中的主键号')->index();
            $table->string('pic1')->comment('图片1');
            $table->string('pic2')->comment('图片2');
            $table->string('pic3')->comment('图片3');
            $table->string('pic4')->comment('图片4');
            $table->string('pic5')->comment('图片5');
            $table->string('pic6')->comment('图片6');
            $table->string('pic7')->comment('图片7');
            $table->string('pic8')->comment('图片8');
            $table->string('pic9')->comment('图片9');
        });
        */
    }
}
