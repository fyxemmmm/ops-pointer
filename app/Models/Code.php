<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 16:30
 */

namespace App\Models;

class Code {

    const SUCC = 0;

    /**
     * http相关错误
     */
    const ERR_HTTP_UNAUTHORIZED = 401;
    const ERR_HTTP_FOBIDDEN = 403;
    const ERR_HTTP_NOTFOUND = 404;
    const ERR_SERVER_INTERNAL = 500;

    /**
     * 1000 系统级别错误
     */
    const ERR_QUERY = 1001;
    const ERR_DB = 1002;
    const ERR_PARAMS = 1003;
    const ERR_MODEL = 1004;
    const ERR_FILEUPLOAD = 1005;
    const ERR_COMPANYNAME = 1006;
    const ERR_PERM = 1007;
    const ERR_EXCEL_COLUMN = 1008;

    /**
     * 10000 资产相关业务
     */
    const ERR_CONTENT_CATE = 10001;
    const ERR_CONTENT_NUMBER = 10002;
    const ERR_CONTENT_FIELD = 10003;
    const ERR_NUMBER_EXISTS = 10004;
    const ERR_NUMBER_NOT_EXISTS = 10005;
    const ERR_LISTFIELDS = 10006;
    const ERR_ASSETS_RELATE_NOT_EXISTS = 10007;
    const ERR_ASSETS_FIELD_TYPE = 10008;
    const ERR_NUMBER_EXISTS_UP = 10009;
    const ERR_NUMBER_DUP = 10010;
    const ERR_EXPORT = 10011;
    const ERR_EXPORT_EMPTY = 10012;
    const ERR_EXPORT_ZIP = 10013;
    const ERR_SUBCATE_EXIST = 10014;
    const ERR_SUBCATE_DEVICE_EXIST = 10015;
    const ERR_SUBCATE_PID = 10016;
    const ERR_HOST_BIND = 10017;
    const ERR_MONITOR_EXISTS = 10018;
    const ERR_FIELD_INUSE = 10019;
    const ERR_ASSETS_USING = 10020;
    const ERR_USER_CATEGORY = 10021;
    const ERR_ASSIGN_USER_CATEGORY = 10022;
    const ERR_QRCODE = 10023;
    const ERR_EMPTYASSETS = 10024;
    const ERR_ROOM_NOT_EMPTY = 10025;
    const ERR_DPT_NOT_EMPTY = 10026;
    const ERR_BUILDING_NOT_EMPTY = 10027;
    const ERR_DATA_ALREADY_EXISTS = 10028;
    const ERR_MONITOR_NOT_IDS = 10029;
    const ERR_DICT_NOT_EMPTY = 10030;
    const ERR_NOT_FREE = 10031;
    const ERR_ASSETS_PARAMS = 10032;


    /**
     * 10100 事件类别
     */
    const ERR_EVENT_CATE = 10101;
    const ERR_EVENT_USER = 10102;
    const ERR_EVENT_DEVICECATE = 10103;
    const ERR_EVENT_NODEVICE = 10104;
    const ERR_EVENT_STATE = 10105;
    const ERR_EVENT_FIELD_REQUIRE = 10106;
    const ERR_EVENT_ASSIGN = 10107;
    const ERR_ASSETS_BREAK = 10108;
    const ERR_ASSETS_TPORT_EMPTY = 10109;
    const ERR_ASSETS_CPORT_EMPTY = 10110;
    const ERR_ASSETS_PORT_USE = 10111;
    const ERR_ASSETS_UNIT = 10112;
    const ERR_ASSETS_RACK_POS = 10113;
    const ERR_ASSETS_RACK_SIZE = 10114;
    const ERR_ASSETS_IN_EVENT = 10115;
    const ERR_EVENT_PERM = 10116;

    const ERR_COMMENTED = 10117;
    const ERR_COMMENT_CONTENT = 10118;
    const ERR_EVENT_NOT = 10119;
    const ERR_UNFINISHED = 10120;
    const ERR_FIRST_LOGIN = 10121;
    const ERR_PARAM_EMPTY = 10122;
    const ERR_OPERATION = 10123;
    const ERR_REPEAT_SUBMIT = 10124;
    const ERR_OPERATION_ACCESS_NOT = 10125;
    const ERR_CLOSE_ACCESS_NOT = 10126;
    const ERR_CONTENT_EMPTY = 10127;
    const ERR_FEEDBACK_UPPER_LIMIT = 10128;
    const ERR_EVENT_FINISH_NOT = 10129;
    const ERR_DEVICE_NOT_INUSE = 10130;
    const ERR_WX_LOGIN_ACCOUNT = 10131;
    const ERR_EVENT_RECEIPT = 10132;

    const ERR_WEEK_TIME = 10133;
    const ERR_TIME_EXCEED_WEEK = 10134;
    const ERR_ASSETS_PORT_SAME = 10135;
    const ERR_ASSETS_ZBXHOST_NOT = 10136;
    const ERR_ASSETS_ZBXHOST_GET = 10137;
    const ERR_WXUSER_NOTUSER = 10138;
    const ERR_USERLEADER_RECEIPT_DISPATCH = 10139;
    const ERR_WXLOGIN_ADMIN = 10140;
    const ERR_WX_REGISTER_CLOSE = 10141;
    const ERR_NOT_USER = 10142;
    const ERR_EVENT_ADD = 10143;
    const ERR_START_DATE_EMPTY = 10144;
    const ERR_MAX_YEAR = 10145;
    const ERR_BIND_ASSETS_NOT = 10146;
    const ERR_ASSETS_IS_UNTIE = 10147;
    const ERR_ADD_MONITOR_FAIL = 10148;
    const ERR_UPDATE_MONITOR_FAIL = 10149;
    const ERR_DEL_MONITOR_FAIL = 10150;
    const ERR_GET_MONITOR_FAIL = 10151;
    const ERR_PAGE_TOO_MUCH = 10152;
    const ERR_GET_MONITOR_DATA = 10153;
    const ERR_HW_NOT_RESPONSE_DATA = 10154;
    const ERR_MONITOR_RETURN_ERRORS = 10155;
    const ERR_MONITOR_RETURN_ERRCODE = 10156;
    const ERR_MONITOR_RETURN_MSSSAGE = 10157;
    const ERR_MONITOR_RETURN_NULL = 10158;
    const ERR_GET_MONITOR_TOKEN_ERR = 10159;
    const ERR_PAGE_TOO_MUCH100 = 10160;
    const ERR_MONITOR_NOT_MATCH = 10161;
    const ERR_ASSET_CATEGORY_NOT = 10162;
    const ERR_NOT_REPORTDATE = 10163;
    const ERR_ALREADY_ASSIGN = 10164;
    const ERR_UPDATE_ASSIGN_EVENTOA = 10165;
    const ERR_USER_REPORT_OAEVENT = 10166;
    const ERR_SUSPEND_OPENED = 10167;
    const ERR_SUSPEND_CLOSEED = 10168;
    const ERR_STATE_NOT_SUSPEND = 10169;
    const ERR_EVENT_FIELD_LENGTH = 10170;
    const ERR_EVENT_FIELD_MAX = 10171;
    const ERR_EVENT_FIELD_DICT = 10172;
    const ERR_EVENT_FIELD_DATE = 10173;
    const ERR_SUSPEND_NOT_END = 10174;
    const ERR_EVENT_ASSET_DEL = 10175;
    const ERR_UPDATE = 10176;
    const ERR_OAEVENT_PROCESSER_NO_AUTHORITY = 10177;
    const ERR_WX_LOGIN = 10178;
    const ERR_USER_DELETE = 10179;
    const ERR_MUST_FILL_FILED = 10180;
    const ERR_EXPORT_LIMIT = 10181;

    /**
     * 10200 知识库
     */
    const ERR_KB_PERM = 10201;
    const ERR_KB_PREPARE = 10202;

    /**
     * ZABBIX API错误 11000起
     */
    const ERR_PARAMS_ZABBIX = 11001;
    const ERR_ZABBIX_RET = 11002;
    /**
     * 监控
     */
    const ERR_HW_MONITOR_CLOSEED = 11003;
    const ERR_EM_NOT_DEVICE = 11004;
    const ERR_EM_BIND_DEVICE = 11005;
    const ERR_EM_TEMPLATE_NAME_EMPTY = 11006;
    const ERR_EM_TEMPLATE_CONTENT_EMPTY = 11007;
    const ERR_EM_TEMPLATE_NUM = 11008;
    const ERR_EM_NOT_TEMPLATE_ID = 11009;
    const ERR_EM_TEMPLATE_DELETED = 11010;
    const ERR_EM_MONITOR_CLOSEED = 11011;
    const ERR_MONITOR_DEVICE_MAX = 11012;
    const ERR_M_MONITOR_CLOSEED = 11013;
    const ERR_EM_M_MONITOR_CLOSEED = 11014;
    const ERR_ADD_LINK_FAIL = 11015;
    const ERR_HW_BOARD_NOT_FOUND = 11016;
    const ERR_LINKS_EXIST = 11017;
    const ERR_LINKS_DUP = 11018;
    const ERR_LINKS_NOT_FOUND = 11019;
    const ERR_LINKS_ASSET_NOT_FOUND = 11020;
    const ERR_LINKS_DEL_ERROR = 11021;
    const ERR_ASSET_DEL_ERROR = 11022;
    const ERR_EMPTY_UNBING_MONITOR = 11023;
    const ERR_NOT_SYNC_BIND_LINKS = 11024;
    const ERR_REPORT_DATES_NUM = 11025;


    /**
     * 上传图片
     * @var array
     */
    const ERR_UPLOADING = 12001;
    const ERR_UPLOAD_TYPE = 12002;
    const ERR_UPLOAD_SIZE = 12003;

    /**
     * 认证与用户
     * @var array
     */
    const ERR_NORMAL_LOGIN = 13001;
    const ERR_PASSWD_EXPIRE = 13002;
    const ERR_PASSWD = 13003;
    const ERR_PASSWD_SAME = 13004;
    const ERR_ADMIN_DEL = 13005;
    const ERR_NOT_ENGINEER = 13006;
    const ERR_ENGINEER_EVENTUSE = 13007;

    /**
     * 系统设置
     * @var array
     */
    const ERR_DUP_FIELD = 14001;
    const ERR_MENU_FIELD = 14002;
    const ERR_EDIT_MENU = 14003;

    /**
     * 盘点接口错误码
     */
    const ERR_NO_AUTH = 15001;
    const ERR_NO_PARAMS = 15002;
    const ERR_EXPIRE = 15003;
    const ERR_EMPTY_DATA = 15004;
    const ERR_FORMAT_DATA = 15005;

    /**
     * 通用错误码
     */
    const COMMON_ERROR = 888;

    public static $msgs = [
        self::SUCC => "操作成功",

        self::ERR_HTTP_UNAUTHORIZED => "登陆已过期，请重新登陆",
        self::ERR_HTTP_FOBIDDEN => "无权访问该地址",
        self::ERR_HTTP_NOTFOUND => "请求地址不存在",
        self::ERR_SERVER_INTERNAL => "服务器内部错误",

        self::ERR_QUERY => "数据库操作失败",
        self::ERR_DB => "数据库连接失败",
        self::ERR_PARAMS => "参数验证失败: %s",
        self::ERR_MODEL => "找不到数据",
        self::ERR_FILEUPLOAD => "文件上传出错",
        self::ERR_COMPANYNAME => "错误的公司名称",
        self::ERR_PERM => "你没有该操作权限，请联系管理员",
        self::ERR_EXCEL_COLUMN => "excel文件列数异常",

        self::ERR_CONTENT_CATE => '导入时类型明细错误',
        self::ERR_CONTENT_NUMBER => '资产变更操作必须存在 资产编号 字段',
        self::ERR_CONTENT_FIELD => '当前类型不存在字段: %s ',
        self::ERR_NUMBER_EXISTS => '资产 : %s 已存在，请勿重复操作',
        self::ERR_NUMBER_NOT_EXISTS => '未找到资产: %s ，请确认是否已上架',
        self::ERR_LISTFIELDS => "显示字段参数错误",
        self::ERR_ASSETS_RELATE_NOT_EXISTS => "资产属性 %s 错误，找不到给定数据： %s",
        self::ERR_ASSETS_FIELD_TYPE => "资产属性 %s = %s 错误，数据格式或者长度不正确，请检查",
        self::ERR_NUMBER_EXISTS_UP => "资产 : %s  已上架，请勿重复操作",
        self::ERR_NUMBER_DUP => "导入的资产编号有重复",
        self::ERR_EXPORT => "导出失败，请联系管理员",
        self::ERR_EXPORT_EMPTY => "无内容需要被导出",
        self::ERR_EXPORT_ZIP => "文件打包失败",
        self::ERR_SUBCATE_EXIST => "请先删除该类别下的子类",
        self::ERR_SUBCATE_DEVICE_EXIST => "该类别下还有资产，无法删除",
        self::ERR_SUBCATE_PID => "类别所属不允许修改",
        self::ERR_HOST_BIND => "该资产尚未启用监控，请先配置",
        self::ERR_MONITOR_EXISTS => "该资产已绑定了监控，无法再添加",
        self::ERR_FIELD_INUSE => "字段使用中，无法删除",
        self::ERR_ASSETS_USING => "资产 [%s] 有过上架操作，无法删除",
        self::ERR_USER_CATEGORY => "你当前所属工种无法执行该操作，请联系管理员",
        self::ERR_ASSIGN_USER_CATEGORY => "你当前所指派的工程师无相关工种操作权限，请派发给其他工程师",
        self::ERR_QRCODE => "二维码生成错误",
        self::ERR_EMPTYASSETS => "资产为空",
        self::ERR_ROOM_NOT_EMPTY => "请先删除下属机房",
        self::ERR_DPT_NOT_EMPTY => "请先删除下属科室",
        self::ERR_BUILDING_NOT_EMPTY => "请先删除下属楼",
        self::ERR_DATA_ALREADY_EXISTS => "当前区域已配置过该机房，请重新选择",
        self::ERR_MONITOR_NOT_IDS => "设备ID不能为空",
        self::ERR_DICT_NOT_EMPTY => "该数据下有子数据，无法删除",
        self::ERR_NOT_FREE => "非闲置资产无法报废",
        self::ERR_ASSETS_PARAMS => '参数有误，请检查后重试！',

        self::ERR_EVENT_CATE => "无法创建该类型事件",
        self::ERR_EVENT_USER => "该用户无法处理该事件流程",
        self::ERR_EVENT_DEVICECATE => "初次入库需要指定有效的资产类别",
        self::ERR_EVENT_NODEVICE => "找不到该事件相关的资产信息",
        self::ERR_EVENT_STATE => "当前事件已结束",
        self::ERR_EVENT_FIELD_REQUIRE => "当前参数: [%s] 不应为空",
        self::ERR_EVENT_ASSIGN => "你没有分派事件的权限",
        self::ERR_ASSETS_BREAK => "点位连接只支持使用中的资产",
        self::ERR_ASSETS_TPORT_EMPTY => "当前资产没有电口配置或者电口超出上限",
        self::ERR_ASSETS_CPORT_EMPTY => "当前资产没有光口配置或者光口超出上限",
        self::ERR_ASSETS_PORT_USE   => "端口被占用了，请更换",
        self::ERR_ASSETS_UNIT   => "设备U数错误",
        self::ERR_ASSETS_RACK_POS   => "机柜所在位置已被占用，或者超出机柜高度",
        self::ERR_ASSETS_RACK_SIZE   => "机柜尺寸错误",
        self::ERR_ASSETS_IN_EVENT   => "请先处理完该资产的事件再开始新建",
        self::ERR_EVENT_PERM   => "你没有处理该事件的权限",
        self::ERR_WEEK_TIME => "请检查开始时间结束时间",
        self::ERR_EVENT_RECEIPT => "该事件已经被接单",
        self::ERR_TIME_EXCEED_WEEK => "时间段不能大于7天",
        self::ERR_ASSETS_ZBXHOST_NOT => "未找到该资产绑定的监控主机",
        self::ERR_ASSETS_PORT_SAME => "端口连接不能连接自己",
        self::ERR_EVENT_ADD => "你没有新建事件的权限",
        self::ERR_START_DATE_EMPTY => '开始时间不能为空',
        self::ERR_MAX_YEAR => '最大时间不能大于一年',
        self::ERR_BIND_ASSETS_NOT => '未找到绑定的资产',
        self::ERR_ASSETS_IS_UNTIE => '已解绑，无需重复解绑',
        self::ERR_ADD_MONITOR_FAIL => '添加监控设备失败',
        self::ERR_UPDATE_MONITOR_FAIL => '更新监控设备失败',
        self::ERR_DEL_MONITOR_FAIL => '删除监控设备失败',
        self::ERR_GET_MONITOR_FAIL => '获取监控设备失败',
        self::ERR_GET_MONITOR_DATA => '获取监控数据失败',
        self::ERR_PAGE_TOO_MUCH => '每页最大查询10000条',
        self::ERR_HW_NOT_RESPONSE_DATA => '未获取到返回数据',
        self::ERR_MONITOR_RETURN_ERRORS => '监控返回错误：%s',
        self::ERR_MONITOR_RETURN_ERRCODE => '监控返回错误码',
        self::ERR_MONITOR_RETURN_MSSSAGE => '监控返回错误信息',
        self::ERR_MONITOR_RETURN_NULL => '暂未取到监控数据',
        self::ERR_GET_MONITOR_TOKEN_ERR => '获取监控TOKEN失败',
        self::ERR_PAGE_TOO_MUCH100 => '每页最大查询100条',
        self::ERR_MONITOR_NOT_MATCH => "选定设备不支持该监控",
        self::ERR_ASSET_CATEGORY_NOT => "资产类型不能为空",
        self::ERR_NOT_REPORTDATE => "请选择一份报告",
        self::ERR_ALREADY_ASSIGN    => "该事件已被派单，无需再派单",
        self::ERR_UPDATE_ASSIGN_EVENTOA => "更新接单或派发出错,事件ID: %s",
        self::ERR_USER_REPORT_OAEVENT => "用户上报OA事件失败",
        self::ERR_SUSPEND_OPENED => "事件已挂起，无需重复操作",
        self::ERR_SUSPEND_CLOSEED => "事件已恢复，无需重复操作",
        self::ERR_STATE_NOT_SUSPEND => "当前状态不能使用挂起或恢复",
        self::ERR_EVENT_FIELD_LENGTH => "参数: [%s] 超出最大长度 %s",
        self::ERR_EVENT_FIELD_MAX => "参数: [%s] 超出最大值 %s",
        self::ERR_EVENT_FIELD_DICT => "参数: [%s] 不在允许范围内",
        self::ERR_EVENT_FIELD_DATE => "参数: [%s] 必须是日期格式",
        self::ERR_SUSPEND_NOT_END => "挂起事件无法完成，请先恢复",
        self::ERR_EVENT_ASSET_DEL => "资产 [%s] 有关联事件未完成，请先完成再做删除",
        self::ERR_UPDATE => "更新操作失败",
        self::ERR_OAEVENT_PROCESSER_NO_AUTHORITY => '被派发人没有操作权限',


        self::ERR_KB_PERM   => "你没有该操作权限",
        self::ERR_KB_PREPARE   => "该文章尚未审核或审核未通过",

        //监控
        self::ERR_PARAMS_ZABBIX => '参数有误，请检查后重试！',
        self::ERR_ZABBIX_RET => '监控接口调用失败，请联系管理员！',
        self::ERR_HW_MONITOR_CLOSEED => '监控功能已关闭',
        self::ERR_MONITOR_DEVICE_MAX => '当前已配置两台设备，请先手动去除一台再做配置',
        self::ERR_ADD_LINK_FAIL => "添加线路失败",
        self::ERR_HW_BOARD_NOT_FOUND => "找不到给定端口",
        self::ERR_LINKS_EXIST => "无法解绑：当前设备已配置线路，请先去监控页面删除线路",
        self::ERR_LINKS_DUP => "线路已存在，无法添加或编辑",
        self::ERR_LINKS_NOT_FOUND => "给定线路不存在",
        self::ERR_LINKS_ASSET_NOT_FOUND => "给定线路与设备没有关系",
        self::ERR_LINKS_DEL_ERROR => "线路已被删除",
        self::ERR_ASSET_DEL_ERROR => "设备不存在，请检查",
        self::ERR_EMPTY_UNBING_MONITOR => "设备已绑定或无设备需要绑定",
        self::ERR_NOT_SYNC_BIND_LINKS => "暂无同步绑定线路",

        //环控
        self::ERR_EM_NOT_DEVICE => '设备已解绑或不存在',
        self::ERR_EM_BIND_DEVICE => '设备已绑定，无需重复操作',
        self::ERR_EM_TEMPLATE_NAME_EMPTY => '模板名称不能为空',
        self::ERR_EM_TEMPLATE_CONTENT_EMPTY => '配置项不能为空',
        self::ERR_EM_TEMPLATE_NUM => '最多只能添加 %s 个模板',
        self::ERR_EM_NOT_TEMPLATE_ID => '请设置模板',
        self::ERR_EM_TEMPLATE_DELETED => '操作失败，数据已删除',
        self::ERR_EM_MONITOR_CLOSEED => '环控功能已关闭',
        self::ERR_M_MONITOR_CLOSEED => '监控功能已关闭',
        self::ERR_EM_M_MONITOR_CLOSEED => '环控,监控功能已关闭',
        self::ERR_REPORT_DATES_NUM => '最大只能选择 %s 个时间段',



        //微信端
        self::ERR_COMMENTED => '已评论',
        self::ERR_COMMENT_CONTENT => '评论内容不能为空',
        self::ERR_EVENT_NOT => '未找到该事件',
        self::ERR_UNFINISHED => '事件未完成，不能评论',
        self::ERR_FIRST_LOGIN => '第一次登录，请输入验证码',
        self::ERR_PARAM_EMPTY => '参数不能为空',
        self::ERR_OPERATION => '操作失败',
        self::ERR_REPEAT_SUBMIT => '请勿重复提交',
        self::ERR_OPERATION_ACCESS_NOT => '你没有该操作权限',
        self::ERR_CLOSE_ACCESS_NOT => '你没有关闭权限，请联系主管',
        self::ERR_DEVICE_NOT_INUSE => "无法对不在使用中的资产进行操作",
        self::ERR_CONTENT_EMPTY => '内容不能为空',
        self::ERR_FEEDBACK_UPPER_LIMIT => '意见反馈已达到上限',
        self::ERR_EVENT_FINISH_NOT => '事件未完成，请先完成事件处理',
        self::ERR_WX_LOGIN_ACCOUNT => '登录账号与绑定微信不匹配，请检查账号',
        self::ERR_WXUSER_NOTUSER => '登录账号与绑定微信不匹配，请检查账号是否已绑定',
        self::ERR_USERLEADER_RECEIPT_DISPATCH => '用户主管接单或派发出错',
        self::ERR_WXLOGIN_ADMIN => '该用户不能登录',
        self::ERR_WX_REGISTER_CLOSE => '请联系管理员注册后再次登录',
        self::ERR_NOT_USER => '该用户未注册，请联系管理员',
        self::ERR_WX_LOGIN => '登录失败，请重试',
        self::ERR_USER_DELETE => '用户已删除，请联系管理员',
        self::ERR_MUST_FILL_FILED => '请输入必填项',
        self::ERR_EXPORT_LIMIT => "最大只能导出 %s 条数据",

        //上传图片
        self::ERR_UPLOADING => '上传图片出错，请重试！',
        self::ERR_UPLOAD_TYPE => '只能上传PNG、JPG或GIF格式的图片！',
        self::ERR_UPLOAD_SIZE => '上传图片不能大于5MB',

        //认证
        self::ERR_NORMAL_LOGIN => "微信用户无法登陆管理后台",
        self::ERR_PASSWD_EXPIRE => "密码已过期，请修改密码",
        self::ERR_PASSWD => "密码错误",
        self::ERR_PASSWD_SAME => "新密码与旧密码相同",

        self::ERR_DUP_FIELD  => "提交数据有重复字段，请检查",
        self::ERR_ADMIN_DEL  => "管理员不可删除",
        self::ERR_NOT_ENGINEER  => "当前用户非工程师",
        self::ERR_ENGINEER_EVENTUSE  => "该用户所属工种类别 [ %s ] 还有事件未处理完成，无法删除该工种",
        self::ERR_MENU_FIELD  => "该菜单存在子菜单，无法删除",
        self::ERR_EDIT_MENU  => "父级菜单不能为本身",

        /**
         * 盘点接口 api
         */
        self::ERR_NO_AUTH => "权限验证失败",
        self::ERR_NO_PARAMS => "缺少必要参数",
        self::ERR_EXPIRE => "该请求已过期",
        self::ERR_EMPTY_DATA => "无数据",
        self::ERR_FORMAT_DATA => "数据格式错误",

        /**
         * 通用错误码
         */
        self::COMMON_ERROR => "%s"

    ];

    protected static $code;

    protected static $msg;

    protected static $detail;

    /**
     * @param $code
     * @param null $msg
     * @param array $params
     */
    public static function setCode($code, $msg = null, $params = []) {
        self::$code = $code = (int)$code;
        if ( null == $msg ) {
            if ( isset(self::$msgs[$code]) ) {
                if(!empty($params)) {
                    array_unshift($params, self::$msgs[$code]);
                    self::$msg = call_user_func_array("sprintf", $params);
                }
                else {
                    self::$msg = self::$msgs[$code];
                }
            }
            else {
                self::$msg = "未定义";
            }
        }
        else {
            self::$msg = $msg;
        }
        if( $code !== self::SUCC ) {
            //save log
        }
    }

    /**
     * @param $detail
     */
    public static function setDetail($detail) {
        self::$detail = $detail;
    }

    /**
     * @return array
     */
    public static function getCode() {
        if ( is_null(self::$code) ){
            self::setCode(Code::SUCC);
        }
        return [self::$code, self::$msg];
    }

    public static function getDetail() {
        return self::$detail;
    }

}