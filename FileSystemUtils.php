<?php

namespace Nouni\FileSystem\Utils;


class FileSystemUtils
{

    private function __construct()
    {

    }

    /**
     * @param $source_dir
     * @param array $exclude
     * @param int $directory_depth
     * @param bool $hidden
     * @return array|bool
     */
    static function listFiles($source_dir, $exclude = array(), $directory_depth = 0, $hidden = FALSE)
    {
        if ($fp = @opendir($source_dir)) {
            $filedata = array();
            $new_depth = $directory_depth - 1;
            $source_dir = rtrim($source_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            while (FALSE !== ($file = readdir($fp))) {
                // Remove '.', '..', and hidden files [optional]
                if (!trim($file, '.') OR ($hidden == FALSE && $file[0] == '.')) {
                    continue;
                }

                if (($directory_depth < 1 OR $new_depth > 0) && @is_dir($source_dir . $file)) {
                    $filedata[$file] = self::listFiles($source_dir . $file . DIRECTORY_SEPARATOR, $exclude, $new_depth, $hidden);
                } elseif (@is_file($source_dir . $file) && !in_array($file, $exclude)) {
                    $filedata[] = $file;
                }
            }

            closedir($fp);
            return $filedata;
        }

        return FALSE;
    }

    /**
     * @param $source_dir
     * @param array $exclude
     * @param int $directory_depth
     * @param bool $hidden
     * @return array|bool
     */
    static function listDirs($source_dir, $exclude = array(), $directory_depth = 0, $hidden = FALSE)
    {
        if ($fp = @opendir($source_dir)) {
            $filedata = array();
            $new_depth = $directory_depth - 1;
            $source_dir = rtrim($source_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            while (FALSE !== ($file = readdir($fp))) {
                // Remove '.', '..', and hidden files [optional]
                if (!trim($file, '.') OR ($hidden == FALSE && $file[0] == '.')) {
                    continue;
                }
                if (!@is_dir($source_dir . $file))
                    continue;
                if (in_array($file, $exclude))
                    continue;
                if (($directory_depth < 1 OR $new_depth > 0)) {
                    $filedata[$file] = self::listDirs($source_dir . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR, $exclude, $new_depth, $hidden);
                } else {
                    $filedata[] = $file;
                }
            }

            closedir($fp);
            return $filedata;
        }

        return FALSE;
    }

    /**
     * Create a Directory Map
     *
     * Reads the specified directory and builds an array
     * representation of it.  Sub-folders contained with the
     * directory will be mapped as well.
     *
     * @access    public
     * @param    string    path to source
     * @param    int        depth of directories to traverse (0 = fully recursive, 1 = current dir, etc)
     * @return    array
     * @author        ExpressionEngine Dev Team
     * @copyright    Copyright (c) 2008 - 2011, EllisLab, Inc.
     */
    static function directoryMap($source_dir, $directory_depth = 0, $hidden = FALSE)
    {
        if ($fp = @opendir($source_dir)) {
            $filedata = array();
            $new_depth = $directory_depth - 1;
            $source_dir = rtrim($source_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            while (FALSE !== ($file = readdir($fp))) {
                // Remove '.', '..', and hidden files [optional]
                if (!trim($file, '.') OR ($hidden == FALSE && $file[0] == '.')) {
                    continue;
                }

                if (($directory_depth < 1 OR $new_depth > 0) && @is_dir($source_dir . $file)) {
                    $filedata[$file] = self::directoryMap($source_dir . $file . DIRECTORY_SEPARATOR, $new_depth, $hidden);
                } else {
                    $filedata[] = $file;
                }
            }

            closedir($fp);
            return $filedata;
        }

        return FALSE;
    }

    static function listeDirInDir($dir_path, $absolute_path = false)
    {
        $dirs = FileSystemUtils::listDirs($dir_path);
        $dirs = array_keys($dirs);
        if ($absolute_path) {
            $dirs = array_map(function ($d) use ($dir_path) {
                return $dir_path . DIRECTORY_SEPARATOR . $d;
            }, $dirs);
        }
        return $dirs;
    }

    /**
     * @param $dir_path
     * @param string $pattern
     * @param bool $absolute_path
     * @return array|bool
     */
    static function listeFilesInDir($dir_path, $pattern = "*", $absolute_path = false)
    {
        $files = FileSystemUtils::listFiles($dir_path);
        if ($pattern != "*") {
            $files = array_filter($files, function ($f, $pattern) {
                return preg_match("/$pattern/", $f);
            });
        }
        if ($absolute_path) {
            $files = array_map(function ($f) use ($dir_path) {
                $rp = $dir_path . DIRECTORY_SEPARATOR . $f;
                return $rp;
            }, $files);
        }
        return $files;
    }

    /**
     * Fait une liste détaillée des fichiers d'un dossier
     * return array of type :
     * ['isdir'=>true/false, 'perms'=>, 'owner'=>, 'group'=>, 'size'=>taille en octets, 'month'=>, 'day'=>, 'time/year'=>, 'name'=>]
     * isdir, name, size are mondatory
     * @access    public
     * @param string $rempath
     * @param bool $juste_dir_and_file si true on return seulement les dossiers et fichiers
     * @return    array
     */
    static function list_files_details($rempath = '.', $juste_dir_and_file = false)
    {
        $path = $rempath;
        if (!static::dir_exists($path)) return array();
        $return = shell_exec("ls -al $path");
        if ($return) {
            $parsed = array();
            $res_arr = explode("\n", $return);
            $i = 0;
            foreach ($res_arr as $current) {
                $split = preg_split("[ ]", $current, 9, PREG_SPLIT_NO_EMPTY);
                /*
                 * $split[0]{0} :
                 *   “-” pour un fichier normal (ex: /etc/cups/command.types)
                 *   “d” pour un répertoire (ex: /etc/cups)
                 *   “c” pour un périphérique “caractère” (ex: un modem comme /dev/rtc)
                 *   “b” pour un périphérique “bloc” (ex: un disque comme /dev/hda ou /dev/sda)
                 *   “l” pour un lien symbolique (ex: /boot/vmlinuz)
                 *   “s” pour une socket locale (ex:/dev/log)
                 *   “p” pour un tube nommé (ex: /dev/bootplash ou /dev/xconsole)
                 */
                if ($split[0] != "total" AND
                    $juste_dir_and_file AND
                    in_array($split[0]{0}, array('d', '-')) AND
                    !in_array($split[8], array('.', '..'))
                ) {
                    $parsed[$i]['isdir'] = $split[0]{0} === "d";
                    $parsed[$i]['perms'] = $split[0];
                    //$parsed[$i]['number'] = $split[1];//(sans intérêt courant: il s'agit d'un comptage de liaisons)
                    $parsed[$i]['owner'] = $split[2];
                    $parsed[$i]['group'] = $split[3];
                    $parsed[$i]['size'] = $split[4];
                    $parsed[$i]['month'] = $split[5];
                    $parsed[$i]['day'] = $split[6];
                    $parsed[$i]['time/year'] = $split[7];
                    $parsed[$i]['name'] = $split[8];
                    $i++;
                }
            }
            return $parsed;
        } else return array();
    }

    /**
     * @param string $path
     * @return bool
     */
    static function dir_exists($path = '')
    {
        if (file_exists($path) AND is_dir($path)) return true;
        else return false;
    }

    /**
     * Permet de synchroniser le dossier $locpath avec le dossier $rempath
     * C'est l'opération inverse de la fonction mirror
     * @param string $from_dir
     * @param string $to_dir dossier à synchroniser avec le dossier $from_dir
     * @throws Exception
     */
    static function synchronise($from_dir, $to_dir)
    {
        static::_synchronise($from_dir, $to_dir, false);
    }

    /**
     * @param string $from_dir
     * @param string $to_dir
     * @param bool $create_local_dir si true on creer le dossier local s'il n'existe pas
     * @throws Exception
     */
    static protected function _synchronise($from_dir, $to_dir, $create_local_dir = false)
    {
        $to_dir = static::normalizePath($to_dir);
        $from_dir = static::normalizePath($from_dir);

        //Verifier est-ce que les dossiers existent
        if (!$create_local_dir AND !is_dir($to_dir))
            throw new \Exception(__FUNCTION__ . ' le path ' . $to_dir . ' n existe pas');
        if (!static::isdir($from_dir))
            throw new \Exception(__FUNCTION__ . ' le remote path ' . $from_dir . ' n existe pas');

        //Récuprérer le contenu du dossier distant
        $files = static::list_files_details($from_dir, true);
        //Sychroniser chaque dossier de façon récursive
        foreach ($files as $key => $file) {
            if ($file['isdir']) {
                $to_file_path = $to_dir . DIRECTORY_SEPARATOR . $file['name'];
                if (!is_dir($to_file_path)) {
                    @mkdir($to_file_path, 0777, true);
                    if (!is_dir($to_file_path)) continue;
                }
                static::_synchronise($from_dir . DIRECTORY_SEPARATOR . $file['name'], $to_file_path, true);
            } else {
                $to_file = $to_dir . DIRECTORY_SEPARATOR . $file['name'];
                if (!file_exists($to_file)) {
                    @copy($from_dir . DIRECTORY_SEPARATOR . $file['name'], $to_file);
                }
            }
        }
    }

    /**
     * Normaliser le chemin d'un dossier en :
     * - eliminant les DIRECTORY_SEPARATOR de la fin du path
     * - elmiminer les DIRECTORY_SEPARATOR multiple du path
     * @param string $path
     * @return string
     * @source @link(http://php.net/manual/en/function.realpath.php)
     */
    static function normalizePath($path = '')
    {
        if (!is_string($path) OR trim($path) === '') return $path;
        $parts = array();// Array to build a new path from the good parts
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);// Replace backslashes with DIRECTORY_SEPARATOR
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);// Replace forwardslashes with DIRECTORY_SEPARATOR
        $path = preg_replace('/\/+/', DIRECTORY_SEPARATOR, $path);// Combine multiple slashes into a single slash
        $path = preg_replace('/\\+/', DIRECTORY_SEPARATOR, $path);// Combine multiple slashes into a single slash
        $segments = explode(DIRECTORY_SEPARATOR, $path);// Collect path segments

        foreach ($segments as $segment) {
            if ($segment != '.') {
                $test = array_pop($parts);
                if (is_null($test))
                    $parts[] = $segment;
                else if ($segment == '..') {
                    if ($test == '..')
                        $parts[] = $test;

                    if ($test == '..' || $test == '')
                        $parts[] = $segment;
                } else {
                    $parts[] = $test;
                    $parts[] = $segment;
                }
            }
        }
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Créer la structure du dossier donné par la $config dans le dossier $in_dir
     * le param $config est un tableau d'element au format :
     * ['name'=>, 'subdirs'=>[], 'indexfile'=>true/false]
     * Les subdirs sont des configs aussi
     * @param string $in_dir
     * @param array $config
     * @throws Exception
     */
    static function create_dirs($in_dir, array $config)
    {
        if (!is_dir($in_dir)) throw new Exception("Le dossier $in_dir n'existe pas.");
        $created_files_for_rollback = [];

        /**
         * @param string $in_dir
         * @param array $config
         * @param array $created_files
         * @throws Exception
         */
        $creation_function = function ($in_dir, array $config, array &$created_files) use (&$creation_function) {
            if (!is_dir($in_dir)) throw new Exception("Le dossier $in_dir n'existe pas.");
            foreach ($config as $key => $value) {
                try {
                    //Dossier :
                    if (array_key_exists('name', $value) AND
                        is_string($value['name']) AND !empty($value['name'])
                    ) {
                        $dir = $in_dir . DIRECTORY_SEPARATOR . $value['name'];
                        if (file_exists($dir)) throw new Exception("Le dossier $dir existe deja");
                        else mkdir($dir);
                        $created_files[] = $dir;
                    } else throw new Exception("Le nom du dossier qui sera cree dans le dossier $in_dir est invalide");

                    //Index file :
                    if (array_key_exists('indexfile', $value) AND
                        $value['indexfile'] == true AND !empty($value['indexfile'])
                    ) {
                        $indexfile = $dir . DIRECTORY_SEPARATOR . 'index.php';
                        if (file_exists($indexfile)) throw new Exception("Le fichier $indexfile existe deja");
                        else touch($indexfile);
                        $created_files[] = $indexfile;
                    }

                    //Subdirectories :
                    if (array_key_exists('subdirs', $value) AND is_array($value['subdirs'])) {
                        $creation_function($dir, $value['subdirs'], $created_files);
                    }
                } catch (Exception $e) {
                    throw $e;
                }
            }
        };
        try {
            $creation_function($in_dir, $config, $created_files_for_rollback);
        } catch (Exception $ex) {
            //Delete created files and directories
            foreach (array_reverse($created_files_for_rollback) as $key => $value) {
                if (is_dir($value)) @rmdir($value);
                elseif (is_file($value)) @unlink($value);
            }
            throw $ex;
        }
    }

    /**
     * Supprimer une arborescence de fichiers et dossiers
     * @param string $dir
     * @return bool
     * @source @link(http://php.net/manual/en/function.rmdir.php)
     */
    static function delete_tree($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        $files = array_map(function ($item) use ($dir) {
            return $dir . DIRECTORY_SEPARATOR . $item;
        }, $files);
        foreach ($files as $file) {
            if (is_dir($file)) delete_tree($file);
            else @unlink($file);
        }
        return @rmdir($dir);
    }

    /**
     * @param $dir_path
     * @param callable $predicat
     * @param bool $isRecursive
     * @throws Exception
     */
    static function countFiles($dir_path, Callable $predicat = null, $isRecursive = false)
    {
        if (!file_exists($dir_path) OR !is_dir($dir_path) OR !is_readable($dir_path))
            throw new \Exception("Le dossier {$dir_path} n'existe pas ou n'est readable");
        $func = function ($dir_path, Callable $predicat = null, $isRecursive = false, $cmpt = 0) use (&$func) {
            $files = scandir($dir_path);
            foreach ($files as $file) {
                if (!in_array($file, array('.', '..'))) {
                    $file_path = $dir_path . DIRECTORY_SEPARATOR . $file;
                    if ($isRecursive AND is_dir($file_path)) {
                        $cmpt = $func($file_path, $predicat, $isRecursive, $cmpt);
                    } else {
                        if (!is_null($predicat)) {
                            if ($predicat($file_path, $file)) {
                                $cmpt++;
                            }
                        } else {
                            $cmpt++;
                        }
                    }
                }
            }
            return $cmpt;
        };
        $cmpt = 0;
        return $func($dir_path, $predicat, $isRecursive, $cmpt);
    }

    /**
     * @param $dir_path
     * @param callable $predicat
     * @param bool $isRecursive
     * @throws Exception
     */
    static function listFileNames($dir_path, Callable $predicat = null, $isRecursive = false)
    {
        if (!file_exists($dir_path) OR !is_dir($dir_path) OR !is_readable($dir_path))
            throw new \Exception("Le dossier {$dir_path} n'existe pas ou n'est readable");
        $func = function ($dir_path, Callable $predicat = null, $isRecursive = false, $fileNames = array()) use (&$func) {
            $files = scandir($dir_path);
            foreach ($files as $file) {
                if (!in_array($file, array('.', '..'))) {
                    $file_path = $dir_path . DIRECTORY_SEPARATOR . $file;
                    if ($isRecursive AND is_dir($file_path)) {
                        $fileNames = $func($file_path, $predicat, $isRecursive, $fileNames);
                    } else {
                        if (!is_null($predicat)) {
                            if ($predicat($file_path, $file)) {
                                $fileNames[] = $file;
                            }
                        } else {
                            $fileNames[] = $file;
                        }
                    }
                }
            }
            return $fileNames;
        };
        $fileNames = array();
        return $func($dir_path, $predicat, $isRecursive, $fileNames);
    }
}