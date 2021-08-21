<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/8
 * Time: 18:34
 */


namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeixinUser extends Model
{
    use SoftDeletes;

    protected $table = "weixin_user";

    protected $fillable = [
        "subscribe",//用户是否订阅该公众号标识，
        //值为0时，代表此用户没有关注该公众号，拉取不到其余信息，
        //只有openid和UnionID（在该公众号绑定到了微信开放平台账号时才有）。
        "openid",
        "unionid",
        "nickname",
        "sex",
        "city",
        "country",
        "province",
        "language",
        "headimgurl",//用户头像，最后一个数值代表正方形头像大小（有0、46、64、96、132数值可选，0代表640*640正方形头像），
        //用户没有头像时该项为空。若用户更换头像，原有头像URL将失效。
        "subscribe_time",
        "remark",
        "groupid",
        "tagid_list",
        "subscribe_scene",//返回用户关注的渠道来源，
        //ADD_SCENE_SEARCH 公众号搜索，
        //ADD_SCENE_ACCOUNT_MIGRATION 公众号迁移，
        //ADD_SCENE_PROFILE_CARD 名片分享，
        //ADD_SCENE_QR_CODE 扫描二维码，
        //ADD_SCENEPROFILE LINK 图文页内名称点击，
        //ADD_SCENE_PROFILE_ITEM 图文页右上角菜单，
        //ADD_SCENE_PAID 支付后关注，
        //ADD_SCENE_OTHERS 其他
        "qr_scene",
        "qr_scene_str",
        "userid"
    ];



}