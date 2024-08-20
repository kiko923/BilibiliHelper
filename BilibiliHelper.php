<?php
header('Content-Type: application/json; charset=utf-8');

class BilibiliAPI
{
    private $cookie;
    private $token;
    private $mixinKey;

    public static $qualitys = ['127'=>'8K 超高清', '126'=>'杜比视界', '125'=>'HDR 真彩', '120'=>'4K 超清', '116'=>'1080P 高帧率', '112'=>'1080P 高码率', '80'=>'1080P 高清', '74'=>'720P 高帧率', '64'=>'720P 高清', '48'=>'720P 高清', '32'=>'480P 清晰', '16'=>'360P 流畅', '6'=>'240P 极速'];
    public static $qualitys_audio = ['30216'=>'64K', '30232'=>'132K', '30280'=>'192K'];
    

    public function __construct($cookie = null, $token = null)
    {
        $this->cookie = 'SESSDATA=a03fa38a%2C1731360486%2Cd487a%2A51CjBMrMG_3uFMP-rwYyl8hPSocLZeMTzfvIdjJq76MTWQbZFjyuhIMVgZCmZ4gqGzMkASVlVUU25iZHhVQXRnVXF1TmdKUC1OcFlsdkFiX2NuSjBqVUdUUExkaGpMRHN0RnFQZGJrWkxldE12d3Qyck5Sd3dGUlBONmVuY1hXNTlLX3dRMGJsM1NBIIEC;'; //For WEB
        $this->token = $token; //For APP/TV
    }

    //获取登录信息
    public function login_info()
    {
        $url = 'https://api.bilibili.com/x/web-interface/nav';
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取登录状态失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            return true;
        }elseif($arr['code'] == -101){
            throw new Exception('COOKIE已失效');
        }else{
            throw new Exception('获取登录状态失败 '.$arr['message']);
        }
    }

    //获取用户上传视频信息
    public function ugc_video_info($querystring){
        $url = 'https://api.bilibili.com/x/web-interface/view?'.$querystring;
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频信息失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            return $arr['data'];
        }else{
            throw new Exception('获取视频信息失败：'.$arr['message']);
        }
    }

    //获取正版视频信息
    public function pgc_video_info($ep_id){
        $url = 'https://api.bilibili.com/pgc/view/web/season?ep_id='.$ep_id;
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频信息失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['result']['episodes'])) throw new Exception('获取视频信息失败，返回内容错误');
            $data = null;
            foreach($arr['result']['episodes'] as $row){
                if($ep_id == $row['id']){
                    $data = $row;
                }
            }
            if(empty($data))throw new Exception('获取视频信息失败，未找到对应视频信息');
            return $data;
        }else{
            throw new Exception('获取视频信息失败：'.$arr['message']);
        }
    }

    //获取正版视频信息
    public function pgc_video_info_by_ssid($season_id){
        $url = 'https://api.bilibili.com/pgc/view/web/season?season_id='.$season_id;
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频信息失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['result']['episodes'])) throw new Exception('获取视频信息失败，返回内容错误');
            $data = $arr['result']['episodes'][0];
            if(empty($data))throw new Exception('获取视频信息失败，未找到对应视频信息');
            return $data;
        }else{
            throw new Exception('获取视频信息失败：'.$arr['message']);
        }
    }

    //获取课堂视频信息
    public function pugv_video_info($ep_id){
        $url = 'https://api.bilibili.com/pugv/view/web/season?ep_id='.$ep_id;
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频信息失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['data']['episodes'])) throw new Exception('获取视频信息失败，返回内容错误');
            $data = null;
            foreach($arr['data']['episodes'] as $row){
                if($ep_id == $row['id']){
                    $data = $row;
                }
            }
            if(empty($data))throw new Exception('获取视频信息失败，未找到对应视频信息');
            return $data;
        }else{
            throw new Exception('获取视频信息失败：'.$arr['message']);
        }
    }

    //获取视频弹幕
    public function get_video_comment($cid){
        $danmu_xml = $this->curl('https://comment.bilibili.com/'.$cid.'.xml');
        if(!$danmu_xml){
            return json_encode(array('status' => 'error', 'message' => '获取弹幕内容失败'), JSON_UNESCAPED_UNICODE);
        }
        $dom = new \DOMDocument();
        $dom->loadXML($danmu_xml);
        $result = $this->getArray($dom->documentElement);
        return isset($result['d']) ? $result['d'] : [];
    }

    //用户上传视频解析（支持外链）
    public function get_video_url($aid, $cid){
        $param = [
            'avid' => $aid,
            'cid' => $cid,
            'qn' => '120',
            'otype' => 'json',
            'fourk' => '1',
            'fnver' => '0',
            'fnval' => '128',
            'player' => '3',
            'platform' => 'html5',
            'high_quality' => '1',
        ];
        $url = 'https://api.bilibili.com/x/player/playurl?'.http_build_query($param);
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频下载链接失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['data']['durl'])) throw new Exception('获取视频下载链接失败，返回内容错误');
            $url = $arr['data']['durl'][0]['url'];
            $size = $arr['data']['durl'][0]['size'];
            $quality = $arr['data']['support_formats'][0]['new_description'];
            return ['url'=>$url, 'size'=>$size, 'quality'=>$quality, 'format'=>$arr['data']['format'], 'codec'=>$this->get_codec($arr['data']['video_codecid'])];
        }else{
            throw new Exception('获取视频下载链接失败 '.$arr['message']);
        }
    }

    //用户上传视频解析
    public function ugc_video_parse($aid, $cid){
        $param = [
            'avid' => $aid,
            'cid' => $cid,
            'qn' => '0',
            'type' => '',
            'otype' => 'json',
            'fourk' => '1',
            'fnver' => '0',
            'fnval' => '4048',
        ];
        $url = 'https://api.bilibili.com/x/player/playurl?'.http_build_query($param);
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频下载链接失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['data']['dash'])) throw new Exception('获取视频下载链接失败，返回内容错误');
            return $this->video_data_handle($arr['data']);
        }else{
            throw new Exception('获取视频下载链接失败 '.$arr['message']);
        }
    }

    //用户上传视频解析（TV接口）
    public function ugc_video_parse_tv($aid, $cid){
        $param = [
            'avid' => $aid,
            'cid' => $cid,
            'qn' => '0',
            'type' => '',
            'otype' => 'json',
            'fnver' => '0',
            'fnval' => '4048',
            'device' => 'android',
            'platform' => 'android',
            'mobi_app' => 'android_tv_yst',
            'npcybs' => '0',
            'force_host' => '2',
            'build' => '102801',
        ];
        if($this->token){
            $param['access_key'] = $this->token;
        }
        $url = 'https://api.snm0516.aisee.tv/x/tv/ugc/playurl?'.http_build_query($param);
        $ret = $this->curl($url);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频下载链接失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['dash'])) throw new Exception('获取视频下载链接失败，返回内容错误');
            return $this->video_data_handle($arr);
        }else{
            throw new Exception('获取视频下载链接失败 '.$arr['message']);
        }
    }

    //正版视频解析
    public function pgc_video_parse($aid, $cid, $epid, $is_cheese=false){
        $param = [
            'avid' => $aid,
            'cid' => $cid,
            'qn' => '0',
            'type' => '',
            'otype' => 'json',
            'fourk' => '1',
            'fnver' => '0',
            'fnval' => '4048',
            'module' => 'bangumi',
            'ep_id' => $epid,
            'session' => ''
        ];
        $url = 'https://api.bilibili.com/pgc/player/web/playurl?'.http_build_query($param);
        if($is_cheese){
            $url = str_replace('/pgc/','/pugv/',$url);
        }
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频下载链接失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['result']['dash'])) throw new Exception('获取视频下载链接失败，返回内容错误');
            return $this->video_data_handle($arr['result']);
        }elseif($arr['code'] == -10403 && !$is_cheese){
            $url = 'https://www.bilibili.com/bangumi/play/ep'.$epid;
            $ret = $this->curl($url, null, $this->cookie.';CURRENT_FNVAL=4048;');
            preg_match('!window\.__playinfo__=([\s\S]*?)<\/script>!',$ret,$match);
            if(isset($match[1])){
                $arr = json_decode($match[1], true);
            }else{
                throw new Exception('获取视频下载链接失败 '.$arr['message']);
            }
        }else{
            throw new Exception('获取视频下载链接失败 '.$arr['message']);
        }
    }

    //正版视频解析（TV接口）
    public function pgc_video_parse_tv($aid, $cid, $epid, $is_cheese=false){
        $param = [
            'appkey' => '4409e2ce8ffd12b8',
            'aid' => $aid,
            'cid' => $cid,
            'qn' => '0',
            'module' => 'bangumi',
            'ep_id' => $epid,
            'expire' => '0',
            'fnval' => '80',
            'fnver' => '0',
            'fourk' => '1',
            'mid' => '0',
            'otype' => 'json',
            'device' => 'android',
            'platform' => 'android',
            'mobi_app' => 'android_tv_yst',
            'npcybs' => '0',
            'build' => '102801',
            'ts' => time()  
        ];
        if($this->token){
            $param['access_key'] = $this->token;
        }
        $param['sign'] = $this->tv_get_sign($param);
        $url = 'https://api.snm0516.aisee.tv/pgc/player/api/playurltv?'.http_build_query($param);
        if($is_cheese){
            $url = str_replace('/pgc/','/pugv/',$url);
        }
        $ret = $this->curl($url);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取视频下载链接失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['dash'])) throw new Exception('获取视频下载链接失败，返回内容错误');
            return $this->video_data_handle($arr);
        }else{
            throw new Exception('获取视频下载链接失败 '.$arr['message']);
        }
    }

    private function video_data_handle($data){
        $video = [];
        $audio = [];
        $timelength = round($data['timelength']/1000);
        if($data['dash']['video']){
            foreach($data['dash']['video'] as $row){
                if(preg_match('!://(.*:\\d+)/!',$row['base_url'],$match)){ //替换PCDN
                    $row['base_url'] = str_replace($match[1], 'upos-sz-mirrorcoso1.bilivideo.com', $row['base_url']);
                }
                $size = round($timelength * $row['bandwidth'] / 8);
                $video[] = ['url'=>$row['base_url'], 'quality'=>self::$qualitys[$row['id']], 'bandwidth'=>round($row['bandwidth']/1000), 'size' => $size, 'codec'=>$this->get_codec($row['codecid']), 'ratio'=>$row['width'].'×'.$row['height'], 'fps'=>$row['frame_rate']];
            }
        }
        if($data['dash']['audio']){
            foreach($data['dash']['audio'] as $row){
                $size = round($timelength * $row['bandwidth'] / 8);
                $audio[] = ['url'=>$row['base_url'], 'quality'=>self::$qualitys_audio[$row['id']], 'bandwidth'=>round($row['bandwidth']/1000), 'size' => $size, 'codec'=>str_replace(['mp4a.40.2','ec-3'], ['M4A', 'AC3'], $row['codecs'])];
            }
        }
        return ['video'=>$video, 'audio'=>$audio];
    }

    //获取音乐信息
    public function get_audio_info($sid){
        $url = 'https://www.bilibili.com/audio/music-service-c/web/song/info?sid='.$sid;
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取音乐信息失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            return $arr['data'];
        }else{
            throw new Exception('获取音乐信息失败：'.$arr['message']);
        }
    }

    //音乐解析
    public function get_audio_url($sid){
        $url = 'https://www.bilibili.com/audio/music-service-c/web/url?sid='.$sid.'&privilege=2&quality=2';
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('获取音乐下载链接失败');
        }elseif(isset($arr['code']) && $arr['code'] == 0){
            if(!isset($arr['data']['cdns'])) throw new Exception('获取音乐下载链接失败，返回内容错误');
            $url = $arr['data']['cdns'][0];
            $size = $arr['data']['size'];
            return ['url'=>$url, 'size'=>$size, 'quality'=>'MP3（192K）'];
        }else{
            throw new Exception('获取音乐下载链接失败：'.$arr['message']);
        }
    }
    
    //视频解析最终结果
    public function get_video_url_results($url){
        $video_url = trim($url);
        if(!$video_url) return json_encode(array('status' => 'error', 'message' => '视频链接不能为空'), JSON_UNESCAPED_UNICODE);
        $vid = $video_url;
        $type = 'ugc';
        $page = 1;
        if(strpos($video_url,'http://')!==false || strpos($video_url,'https://')!==false){
            if(preg_match('!/BV(\w+)!i',$video_url,$match)){
                $vid = 'BV'.$match[1];
                $querystring = 'bvid='.$vid;
            }elseif(preg_match('!/av(\d{1,})!',$video_url,$match)){
                $vid = $match[1];
                $querystring = 'aid='.$vid;
            }elseif(preg_match('!/ep(\d{1,})!',$video_url,$match)){
                $vid = $match[1];
                $type = 'pgc';
                if(strpos($video_url,'/cheese/')!==false){
                    $type = 'pugv';
                }
            }elseif(preg_match('!/ss(\d{1,})!',$video_url,$match)){
                $vid = $match[1];
                $type = 'pgc_ss';
            }elseif(preg_match('!/au(\d{1,})!',$video_url,$match)){
                $vid = $match[1];
                $type = 'audio';
            }else{
                return json_encode(array('status' => 'error', 'message' => '视频链接输入格式错误'), JSON_UNESCAPED_UNICODE);
            }
            if($type == 'ugc' && preg_match('!p=(\d{1,})!',$video_url,$match)){
                $page = $match[1];
            }
        }else{
            if(substr($vid,0,2) == 'av' && is_numeric(substr($vid,2))){
                $querystring = 'aid='.substr($vid,2);
            }elseif(substr($vid,0,2) == 'ep' && is_numeric(substr($vid,2))){
                $vid = substr($vid,2);
                $type = 'pgc';
            }elseif(substr($vid,0,2) == 'ss' && is_numeric(substr($vid,2))){
                $vid = substr($vid,2);
                $type = 'pgc_ss';
            }elseif(substr($vid,0,2) == 'BV' && preg_match('/^[a-zA-Z0-9]+$/',$vid)){
                $querystring = 'bvid='.$vid;
            }elseif(substr($vid,0,2) == 'au' && is_numeric(substr($vid,2))){
                $vid = substr($vid,2);
                $type = 'audio';
            }else{
                return json_encode(array('status' => 'error', 'message' => '视频链接输入格式错误'), JSON_UNESCAPED_UNICODE);
            }
        }

        $bilibili = new BilibiliAPI();
        try{
            if($type == 'ugc'){
                $video_info = $bilibili->ugc_video_info($querystring);
                $link = 'https://www.bilibili.com/video/'.$video_info['bvid'];
                if(count($video_info['pages']) > 1){
                    foreach($video_info['pages'] as $pagerow){
                        if($page == $pagerow['page']){
                            $video_info['cid'] = $pagerow['cid'];
                            $video_info['duration'] = $pagerow['duration'];
                            $video_info['title'] .= ' - '.$pagerow['part'];
                            $link .= '?p='.$page;
                            continue;
                        }
                    }
                }
                $play_info = $bilibili->get_video_url($video_info['aid'], $video_info['cid']);

                $result = [
                    'type' => 0,
                    'aid' => $video_info['aid'],
                    'cid' => $video_info['cid'],
                    'bvid' => $video_info['bvid'],
                    'link' => $link,
                    'danmu' => 'https://comment.bilibili.com/'.$video_info['cid'].'.xml',
                    'title' => $video_info['title'],
                    'desc' => $video_info['desc'],
                    'pic' => $video_info['pic'],
                    'duration' => $video_info['duration'],
                    'owner' => isset($video_info['owner'])?$video_info['owner']:null,
                    'video' => $play_info,
                ];
            }elseif($type == 'pgc' || $type == 'pgc_ss'){
                $video_info = $type == 'pgc_ss' ? $bilibili->pgc_video_info_by_ssid($vid) : $bilibili->pgc_video_info($vid);

                $result = [
                    'type' => 0,
                    'aid' => $video_info['aid'],
                    'cid' => $video_info['cid'],
                    'bvid' => $video_info['bvid'],
                    'link' => $video_info['link'],
                    'danmu' => 'https://comment.bilibili.com/'.$video_info['cid'].'.xml',
                    'title' => $video_info['share_copy'],
                    'desc' => null,
                    'pic' => $video_info['cover'],
                    'duration' => $video_info['duration'],
                    'owner' => false,
                    'video' => false,
                ];
            }elseif($type == 'audio'){
                $audio_info = $bilibili->get_audio_info($vid);
                $link = 'https://www.bilibili.com/audio/au'.$audio_info['id'];
                $play_info = $bilibili->get_audio_url($audio_info['id']);

                $result = [
                    'type' => 1,
                    'aid' => $audio_info['id'],
                    'link' => $link,
                    'lyric' => $audio_info['lyric'],
                    'title' => $audio_info['title'],
                    'desc' => $audio_info['intro'],
                    'pic' => $audio_info['cover'],
                    'duration' => $audio_info['duration'],
                    'owner' => ['mid'=>$audio_info['uid'], 'name'=>$audio_info['uname']],
                    'video' => $play_info,
                ];
            }else{
                return json_encode(array('status' => 'error', 'message' => '该视频类型不支持在线解析'), JSON_UNESCAPED_UNICODE);
            }
            return json_encode(array('status' => 'success', 'result' => $result), JSON_UNESCAPED_UNICODE);
        }catch(\Exception $e){
            return json_encode(array('status' => 'error', 'message' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }

    private function curl($url,$data=null,$cookie=null,$referer=null){
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        $httpheader[] = "Accept: application/json";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
        $httpheader[] = "Connection: keep-alive";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        if($data){
            if(is_array($data)) $data=http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            curl_setopt($ch, CURLOPT_POST,1);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_REFERER, $referer?$referer:'https://www.bilibili.com/');
        if($cookie){
            curl_setopt($ch,CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36 Edg/95.0.1020.44');
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        $ret=curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    private function get_codec($codecid){
        switch($codecid){
            case 13:
                return 'AV1';break;
            case 12:
                return 'HEVC';break;
            case 7:
                return 'AVC';break;
            default:
                return 'UNKNOWN';break;
        }
    }

    private function tv_get_sign($param){
        $key = '59b43e04ad6965f34319062b478f83dd';
        ksort($param);
        $signstr = http_build_query($param);
        return md5($signstr.$key);
    }

    private function getArray($node) {
        $array = false;
      
        if ($node->hasAttributes()) {
          foreach ($node->attributes as $attr) {
            $array[$attr->nodeName] = $attr->nodeValue;
          }
        }
      
        if ($node->hasChildNodes()) {
          if ($node->childNodes->length == 1) {
            $array[$node->firstChild->nodeName] = $this->getArray($node->firstChild);
          } else {
            foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType != XML_TEXT_NODE) {
              $array[$childNode->nodeName][] = $this->getArray($childNode);
            }
          }
        }
        } else {
          return $node->nodeValue;
        }
        return $array;
    }

    private function encWbi($params){
        $mixin_key = $this->getMixinKey();
        $curr_time = time();
        $chr_filter = "/[!'()*]/";

        $query = [];
        $params['wts'] = $curr_time;

        ksort($params);

        foreach ($params as $key => $value) {
            $value = preg_replace($chr_filter, '', $value);
            $query[] = urlencode($key) . '=' . urlencode($value);
        }

        $query = implode('&', $query);
        $wbi_sign = md5($query . $mixin_key);

        return $query . '&w_rid=' . $wbi_sign;
    }

    private function getMixinKey(){
        if(!empty($this->mixinKey)) return $this->mixinKey;

        $url = 'https://api.bilibili.com/x/web-interface/nav';
        $ret = $this->curl($url, null, $this->cookie);
        $arr = json_decode($ret, true);
        if(!$arr){
            throw new Exception('请求失败');
        }
        if(!isset($arr['data']['wbi_img'])){
            throw new Exception('获取WbiKeys失败');
        }

        $img_url = $arr['data']['wbi_img']['img_url'];
        $sub_url = $arr['data']['wbi_img']['sub_url'];
        $img_key = substr(basename($img_url), 0, strpos(basename($img_url), '.'));
        $sub_key = substr(basename($sub_url), 0, strpos(basename($sub_url), '.'));
        $key = $img_key . $sub_key;

        $mixinKeyEncTab = [
            46, 47, 18, 2, 53, 8, 23, 32, 15, 50, 10, 31, 58, 3, 45, 35, 27, 43, 5, 49,
            33, 9, 42, 19, 29, 28, 14, 39, 12, 38, 41, 13, 37, 48, 7, 16, 24, 55, 40,
            61, 26, 17, 0, 1, 60, 51, 30, 4, 22, 25, 54, 21, 56, 59, 6, 63, 57, 62, 11,
            36, 20, 34, 44, 52
        ];

        $t = '';
        foreach ($mixinKeyEncTab as $n) $t .= $key[$n];
        $this->mixinKey = substr($t, 0, 32);
        return $this->mixinKey;
    }
    
    
}

$bilibili = new BilibiliAPI();
if ((isset($_REQUEST["video"]))) {
    echo $bilibili->get_video_url_results($_REQUEST["url"]);
}
?>