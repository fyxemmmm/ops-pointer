<?php

namespace App\Repositories\Report;

use App\Repositories\BaseRepository;
use App\Models\Code;
use App\Exceptions\ApiException;
use DB;
use Auth;
use Log;
use Session;
use Illuminate\Support\Facades\Schema;


class ExportConfigRepository extends BaseRepository
{
    protected $dbHost;
    protected $dbUsername;
    protected $dbPassword;
    protected $constIncStr;
    protected $databaseStr;
    protected $envStr;
    protected $backUpSqlPath;
    protected $fileStrArr;

    public static $tableArr = [
        'assets_category_fields',   // 资产分类字段（资产配置）
        'assets_fields',    // 资产字段
        'assets_fields_type',   // 资产字段类型
        'assets_category',  // 资产分类
        'engineers',    // 工种配置
        'em_inspection_template',   // 巡检配置
        'menus',    // 菜单管理
        'action_config', // 功能配置
    ];


    public function __construct()
    {
        $this->dbHost = getenv('DB_HOST');
        $this->dbUsername = getenv('DB_USERNAME');
        $this->dbPassword = getenv('DB_PASSWORD');

        $this->constIncStr = config_path() . '/const.inc.php';
        $this->databaseStr = config_path() . '/database.php';
        $this->envStr = base_path() . '/.env';
        $this->backUpSqlPath = database_path() . '/configBackUp';

        // 需要备份的 php 配置文件
        $this->fileStrArr = [
            'const.inc.php' => $this->constIncStr,
            'database.php' => $this->databaseStr,
            '.env' => $this->envStr
        ];


    }

    /**
     * 备份数据表
     */
    public function getSqlBackup($isSecondBak = false){

        $user = Auth::user();

        if(\ConstInc::MULTI_DB && isset($user['db']) && !empty($user['db'])) {
            $dbDatabase = $user->db;
            \DB::setDefaultConnection($dbDatabase); //切换到实际的数据库
        }else{
            $dbDatabase = DB::getDatabaseName();
        }

        $hasTable = [];
        $noHasTable = [];
        foreach (self::$tableArr as $table){
            if (Schema::hasTable($table)) {
                $hasTable[] = $table;
            }else{
                $noHasTable[] = $table;
            }
        }

        if(empty($hasTable)) throw new ApiException(Code::ERR_MODEL, ["请确定数据库中是否存在要导出的数据表"]);

        $tables = implode(' ',$hasTable);

        $dbHost = $this->dbHost;
        $dbUsername = $this->dbUsername;
        $dbPassword = $this->dbPassword;

        // 存放 sql 文件路径
        $backUpSqlPath = $this->backUpSqlPath;
        checkCreateDir($backUpSqlPath);

        if(!$isSecondBak){
            $dumpFileName = $backUpSqlPath . '/' . $dbDatabase . '_bak_' .date('Y-m-d_H-i-s') . '.sql';

            Session::put('firstbak', $dumpFileName);

            // 备份配置 php 文件
            $this->getFileBackup();
        }else{
            $dumpFileName = $backUpSqlPath . '/' . $dbDatabase . '_second_bak_' .date('Y-m-d_H-i-s') . '.sql';
        }

        $command = 'mysqldump --add-drop-table --host=' . $dbHost . ' --user=' . $dbUsername . ' ';
        if($dbPassword) $command .= '--password=' . $dbPassword . ' ';
        $command .= $dbDatabase . ' ';
        $command .= $tables;
        $command .= ' > ' . $dumpFileName;

        system($command);

        $info = '备份了' . $tables . '数据表，sql 文件路径为：' . $dumpFileName;
        Log::info($info);

        $data = [
            'success' => json_encode($hasTable),
            'error' => json_encode($noHasTable),
            'backup_path' => $dumpFileName,
            'config_file' => json_encode($this->fileStrArr),
        ];

        return $data;

    }


    /**
     * 备份配置文件
     */
    public function getFileBackup(){

        checkCreateDir($this->backUpSqlPath);

        foreach ($this->fileStrArr as $k => $v){
            if(file_exists($v)){
                $dest = $this->backUpSqlPath . '/' . $k;
                $isCopy = copy($v,$dest);
                if(!$isCopy){
                    Log::error('文件：' . $v . '备份失败');
                }else{
                    Log::info('文件：' . $v . '备份成功');
                }
            }else{
                log::error('文件：' . $v . '不存在');
            }
        }

    }


    /**
     * 导入 sql 文件
     */
    public function getExecuteSql(){

        $user = Auth::user();

        if(\ConstInc::MULTI_DB && isset($user['db']) && !empty($user['db'])) {
            $dbDatabase = $user->db;
            \DB::setDefaultConnection($dbDatabase); //切换到实际的数据库
        }else{
            $dbDatabase = DB::getDatabaseName();
        }

        // 先备份数据库中的表（第二次备份）
        $result = $this->getSqlBackup($isSecondBak = true);

        // 还原 php 配置文件
        $this->getExecuteFile();


        if(Session::has('firstbak')){
            $sqlBakFilePath = Session::get('firstbak');
            if(!file_exists($sqlBakFilePath)){
                throw new ApiException(Code::ERR_MODEL, ["还原 sql 文件路径不能为空或 sql 文件不存在"]);
            }
        }else{
            throw new ApiException(Code::ERR_MODEL, ["请检查第一次备份的 sql 文件是否存在"]);
        }

        $dbHost = $this->dbHost;
        $dbUsername = $this->dbUsername;
        $dbPassword = $this->dbPassword;

        $command = 'mysql -h' . $dbHost . ' -u' . $dbUsername . ' -p' . $dbPassword;
        $command .= ' ' . $dbDatabase . ' < ';
        $command .= $sqlBakFilePath;
        system($command);

        $info = '还原了' . $sqlBakFilePath . ' sql 文件';
        Log::info($info);

        if(is_array($result)){
            foreach ($result as $k => $v){
                if('backup_path' == $k){
                    $result['second_backup_path'] = $v;
                    unset($result[$k]);
                }
                $result['restore_sql_path'] = $sqlBakFilePath;
            }
        }

        return $result;

    }

    /**
     * 还原配置 php 文件
     */
    public function getExecuteFile(){

        $fileStrArr = $this->fileStrArr;
        foreach ($fileStrArr as $k => $v){
            $source = $this->backUpSqlPath . '/' . $k;
            if(file_exists($source)){
                $isCopy = copy($source,$v);
                if(!$isCopy){
                    Log::error('文件：' . $source . '还原失败');
                }else{
                    Log::info('文件：' . $source . '还原成功');
                }
            }else{
                Log::error('文件：' . $source . '不存在');
            }
        }

    }




}
