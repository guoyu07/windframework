<?php
/**
 * @author Qian Su <aoxue.1988.su.qian@163.com> 2010-11-19
 * @link http://www.phpwind.com
 * @copyright Copyright &copy; 2003-2110 phpwind.com
 * @license 
 */

/**
 * 程序打包工具
 * the last known user to change this file in the repository  <$LastChangedBy$>
 * @author Qian Su <aoxue.1988.su.qian@163.com>
 * @version $Id$ 
 * @package 
 */
class WindPack {
	const STRIP_SELF = 'stripWhiteSpaceBySelf';
	const STRIP_PHP = 'stripWhiteSpaceByPhp';
	const STRIP_TOKEN = 'stripWhiteSpaceByToken';
	private $packList = array ();
	/**
	 * 去除指定文件的注释及空白
	 * @param string $filename 文件名
	 */
	public function stripWhiteSpaceByPhp($filename) {
		return php_strip_whitespace ( $filename );
	}
	
	public function stripWhiteSpaceBySelf($filename, $compress = true) {
		$content = $this->getContentFromFile ( $filename );
		$content = $this->stripComment ( $content, '' );
		$content = $this->stripSpace ( $content, ' ' );
		$content = $this->stripNR ( $content, $compress ? '' : "\n" );
		return $content;
	}
	
	public function stripWhiteSpaceByToken($filename) {
		$content =  $this->getContentFromFile ( $filename );
		$compressContent = '';
		$lastToken = 0;
		foreach ( token_get_all ( $content ) as $key => $token ) {
			if (is_array ( $token )) {
				if (in_array ( $token [0], array (T_COMMENT, T_WHITESPACE,T_DOC_COMMENT ) )) {
					continue;
				}
				$compressContent .= ' '.$token [1];
			} else {
				$compressContent .= $token;
			}
			$lastToken = $token [0];
		}
		return $compressContent;
	}
	/**
	 * 去除注释
	 * @param string $content 要去除的内容
	 * @param string $replace 要替换的文本
	 * @return string
	 */
	public function stripComment($content, $replace = '') {
		return preg_replace ( '/(?:\/\*.*\*\/)*|(?:\/\/[^\r\n]*[\r\n])*/Us', $replace, $content );
	}
	
	/**
	 * 去除换行
	 * @param string $content 要去除的内容
	 * @param string $replace 要替换的文本
	 * @return string
	 */
	public function stripNR($content, $replace = "\n") {
		return preg_replace ( "/[\n\r]+/", $replace, $content );
	}
	
	/**
	 * 去除空格符
	 * @param string $content 要去除的内容
	 * @param string $replace 要替换的文本
	 * @return string
	 */
	public function stripSpace($content, $replace = ' ') {
		return preg_replace ( "/[ ]+/", $replace, $content );
	}
	
	/**
	 * 去除php标识
	 * @param string $content
	 * @param string $replace
	 * @return string
	 */
	public function stripPhpIdentify($content, $replace = '') {
		return preg_replace ( "/(?:<\?(?:php)*)|(\?>)/i", $replace, $content );
	}
	
	/**
	 * 根据指定规则替换指定内容中相应的内容
	 * @param string $content
	 * @param string $rule
	 * @param string $replace
	 * @return string
	 */
	public function stripStrByRule($content, $rule, $replace = '') {
		return preg_replace ( "/$rule/", $replace, $content );
	}
	
	/**
	 * 去除多余的文件导入信息
	 * @param string $content
	 * @param string $replace
	 * @return string
	 */
	public function stripImport($content, $replace = '') {
		$str = preg_match_all ( '/L[\t ]*::[\t ]*import[\t ]*\([\t ]*[\'\"]([^$][\w\.:]+)[\"\'][\t ]*\)[\t ]*/', $content, $matchs );
		if ($matchs [1]) {
			foreach ( $matchs [1] as $key => $value ) {
				$name = substr ( $value, strrpos ( $value, '.' ) + 1 );
				if (preg_match ( "/(abstract[\t ]*|class|interface)[\t ]+$name/i", $content )) {
					$strip = str_replace ( array ('(', ')' ), array ('\(', '\)' ), addslashes ( $matchs [0] [$key] ) ) . '[\t ]*;';
					$content = $this->stripStrByRule ( $content, $strip, $replace );
				}
			}
		}
		return $content;
	}
	
	/**
	 * 取得被打包的文件列表
	 * @return array:
	 */
	public function getPackList() {
		return $this->packList;
	}
	
	public function convertPackList($pack = array(), $samekey = '') {
		static $list = array ();
		$pack = $pack && is_array ( $pack ) ? $pack : $this->getPackList ();
		foreach ( $pack as $key => $value ) {
			if (is_array ( $value )) {
				$this->convertPackList ( $value, $key );
			} else {
				$key = $samekey ? $samekey : $key;
				array_push ( $list, $key . '=' . $value );
			}
		}
		return $list;
	}
	/**
	 *从文件读取内容
	 * @param string $filename 文件名
	 * @return string
	 */
	public function getContentFromFile($filename) {
		if ($this->isFile ( $filename )) {
			$fp = fopen ( $filename, "r" );
			while ( ! feof ( $fp ) ) {
				$line = fgets ( $fp );
				if (in_array ( strlen ( $line ), array (2, 3 ) ) && in_array ( ord ( $line ), array (9, 10, 13 ) ))
					continue;
				$content .= $line;
			}
			fclose ( $fp );
			return $content;
		}
		return false;
	}
	
	/**
	 * 将内容打包的文件
	 * @param string $filename 文件内容
	 * @param string $content  要打包的指定文件的内容
	 * @return string
	 */
	public function writeContentToFile($filename, $content) {
		$fp = fopen ( $filename, "w" );
		fwrite ( $fp, $content );
		fclose ( $fp );
		return true;
	}
	/**
	 * 根据文件后缀得取对应的mime内容
	 * @param string $content 要打包的内容内容
	 * @param string $suffix 文件后缀类型
	 * @return string
	 */
	public function getContentBySuffix($content, $suffix) {
		switch ($suffix) {
			case 'php' :
				$content = '<?php' . $content . '?>';
			default :
				;
		}
		return $content;
	}
	
	/**
	 * @param string $content
	 * @param string $suffix
	 * @param string $other
	 * @return string
	 */
	public function getCommentBySuffix($content, $suffix, $other = '') {
		switch ($suffix) {
			case 'php' :
				$content = "\r\n/**$other\r\n*" . $content . "\r\n*/\r\n";
			default :
				;
		}
		return $content;
	}
	/**
	 * 将指定的数组转化形字符串格式
	 * @param array $pack
	 * @return string
	 */
	public function getPackListAsString($pack = array()) {
		$str = '';
		$packs = $pack ? $pack : $this->getPackList ();
		foreach ( $packs as $key => $value ) {
			$str .= (is_string ( $key ) ? '"' . $key . '"' : $key) . '=>';
			if (is_array ( $value )) {
				$str .= 'array(';
				$str .= $this->getPackListAsString ( $value );
				$str .= ')';
			} else {
				$str .= (is_string ( $value ) ? '"' . $value . '"' : $value) . ',';
			}
		}
		return empty ( $pack ) ? 'array(' . $str . ')' : $str;
	}
	
	/**
	 * @param unknown_type $content
	 * @param unknown_type $sep
	 * @return string
	 */
	public function setImports($content = '', $sep = "\r\n") {
		$packlist = $this->getPackListAsString ();
		$sep = isset ( $sep ) ? $sep : "\r\n";
		return "{$sep}L::setImports(" . $packlist . ");{$sep}" . $content;
	}
	
	/**
	 * 从各个目录中取得对应的每个文件的内容 
	 * @param string $packMethod 打包方式
	 * @param mixed $dir 目录名
	 * @param string $absolutePath 绝对路径名
	 * @param array $ndir 不须要打包的文件夹
	 * @param array $suffix 不须要打包的文件类型
	 * @param array $nfile 不须要打包的文件
	 * @return array
	 */
	public function readContentFromDir($packMethod = WindPack::STRIP_PHP, $dir = array(), $absolutePath = '', $ndir = array('.','..','.svn'), $suffix = array(), $nfile = array()) {
		static $content = array ();
		if (empty ( $dir ) || false === $this->isValidatePackMethod ( $packMethod )) {
			return false;
		}
		$dir = is_array ( $dir ) ? $dir : array ($dir );
		foreach ( $dir as $_dir ) {
			$_dir = is_dir ( $absolutePath ) ? $this->realDir ( $absolutePath ) . $_dir : $_dir;
			if ($this->isDir ( $_dir )) {
				$handle = dir ( $_dir );
				while ( false != ($tmp = $handle->read ()) ) {
					$name = $this->realDir ( $_dir ) . $tmp;
					if ($this->isDir ( $name ) && ! in_array ( $tmp, $ndir )) {
						$this->readContentFromDir ( $packMethod, $name, $absolutePath, $ndir, $suffix, $nfile );
					}
					if ($this->isFile ( $name ) && ! in_array ( $this->getFileSuffix ( $name ), $suffix ) && ! in_array ( $file = $this->getFileName ( $name ), $nfile )) {
						$content [] = $this->$packMethod ( $name );
						$this->setPackList ( $file, $name );
					}
				}
				$handle->close ();
			}
		}
		return $content;
	}
	
	/**
	 * @param mixed $fileList
	 * @param method $packMethod
	 * @param string $absolutePath
	 * @return array:
	 */
	public function readContentFromFile($fileList, $packMethod = WindPack::STRIP_PHP, $absolutePath = '') {
		if (empty ( $fileList ) || false === $this->isValidatePackMethod ( $packMethod )) {
			return false;
		}
		$content = array ();
		$fileList = is_array ( $fileList ) ? $fileList : array ($fileList );
		foreach ( $fileList as $key => $value ) {
			$file = is_dir ( $absolutePath ) ? $this->realDir ( $absolutePath ) . $value : $value;
			if (is_file ( $file )) {
				$content [] = $this->$packMethod ( $file );
				$this->setPackList ( $key, $file );
			}
		}
		return $content;
	}
	
	private function isValidatePackMethod($packMethod) {
		return method_exists ( $this, $packMethod ) && in_array ( $packMethod, array (WindPack::STRIP_PHP, WindPack::STRIP_SELF, WindPack::STRIP_TOKEN ) );
	}
	
	/**
	 * 添加被打包的文件到列表
	 * @param  string $key
	 * @param  string $value
	 */
	private function setPackList($key, $value) {
		if (isset ( $this->packList [$key] )) {
			if (is_array ( $this->packList [$key] )) {
				array_push ( $this->packList [$key], $value );
			} else {
				$tmp_name = $this->packList [$key];
				$this->packList [$key] = array ($tmp_name, $value );
			}
		} else {
			$this->packList [$key] = $value;
		}
	}
	
	/**
	 * 取得真实的目录
	 * @param string $path 路径名
	 * @return string
	 */
	public function realDir($path) {
		if (($pos = strrpos ( $path, DIRECTORY_SEPARATOR )) === strlen ( $path ) - 1) {
			return $path;
		}
		return $path . DIRECTORY_SEPARATOR;
	}
	
	/**
	 * 判断是否是一个文件
	 * @param string $filename 文件名
	 * @return boolean
	 */
	public function isFile($filename) {
		return is_file ( $filename );
	}
	
	/**
	 * 判断是否是一个目录
	 * @param string $dir 目录名
	 * @return boolean
	 */
	public function isDir($dir) {
		return is_dir ( $dir );
	}
	
	/**
	 * 将指定文件类型且指定文件夹下的所指定文件打包成一个易阅读的文件,
	 * @param mixed $dir 要打包的目录
	 * @param string $dst 文件名
	 * @param string $packMethod 打包方式
	 * @param boolean $compress 是否压缩
	 * @param string $absolutePath 文件路径
	 * @param array $ndir 不须要打包的目录
	 * @param array $suffix 不永许打包的文件类型
	 * @return string
	 */
	public function packFromDir($dir, $dst, $packMethod = WindPack::STRIP_PHP, $compress = true, $absolutePath = '', $ndir = array('.','..','.svn'), $suffix = array(), $nfile = array()) {
		if (empty ( $dst ) || empty ( $dir )) {
			return false;
		}
		$suffix = is_array ( $suffix ) ? $suffix : array ($suffix );
		if (! ($content = $this->readContentFromDir ( $packMethod, $dir, $absolutePath, $ndir, $suffix, $nfile ))) {
			return false;
		}
		$fileSuffix = $this->getFileSuffix ( $dst );
		$replace = $compress ? ' ' : "\n";
		$content = implode ( $replace, $content );
		$content = $this->stripNR ( $content, $replace );
		$content = $this->setImports ( $content, $replace );
		$content = $this->stripPhpIdentify ( $content, '' );
		$content = $this->stripImport ( $content, '' );
		$content = $this->getContentBySuffix ( $content, $fileSuffix );
		$this->writeContentToFile ( $dst, $content );
		return true;
	}
	
	/**
	 * @param mixed $fileList
	 * @param string $dst
	 * @param method $packMethod
	 * @param boolean $compress
	 * @param string $absolutePath
	 * @return string|string
	 */
	public function packFromFile($fileList, $dst, $packMethod = WindPack::STRIP_PHP, $compress = true, $absolutePath = '') {
		if (empty ( $dst ) || empty ( $fileList )) {
			return false;
		}
		if (! ($content = $this->readContentFromFile ( $fileList, $packMethod, $absolutePath ))) {
			return false;
		}
		$fileSuffix = $this->getFileSuffix ( $dst );
		$replace = $compress ? ' ' : "\n";
		$content = implode ( $replace, $content );
		$content = $this->stripNR ( $content, $replace );
		$content = $this->setImports ( $content, $replace );
		$content = $this->stripPhpIdentify ( $content, '' );
		$content = $this->stripImport ( $content, '' );
		$content = $this->getContentBySuffix ( $content, $fileSuffix );
		$this->writeContentToFile ( $dst, $content );
		return true;
	}

	public function getFileSuffix($filename) {
		return substr ( $filename, strrpos ( $filename, '.' ) + 1 );
	}
	
	public function getFileName($path, $ifsuffix = false) {
		$filename = substr ( $path, strrpos ( $path, DIRECTORY_SEPARATOR ) + 1 );
		return $ifsuffix ? $filename : substr ( $filename, 0, strrpos ( $filename, '.' ) );
	}

}
