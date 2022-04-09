#! /usr/bin/env python
"""
Patch application before deployment

@Author: felix.liberman@gmail.com

@Usage:
    patch_app.py --conf CONF_FILE.json/YAML --path DEPLOYMENT_BASE_PATH

@Data structures:
    conf-file contain the following info
        vars:
           name1: value
           name2: value
        files:
           - path1/file1
           - path2/file2

    Source code format
        some_code = '{{name1}}';   <<<=== name1 will be replaced with variable value
"""

import sys
import os
import argparse
import yaml
import json

RC_PASS = 0
RC_FAIL = 1

def main():
    """
    Main routine: read args, perform action
    """
    rc = RC_PASS
    args = parseArgs()
    conf = readConf(args.conf)
    execPatch(conf, args.path)
    return rc

def parseArgs():
    """
    Parse input args
    @return: args (namespace)
    """
    parser = argparse.ArgumentParser(
        description='Patch application before deployment')
    parser.add_argument(
        '-c', '--conf', required=True,
        help="configuration file file")
    parser.add_argument(
        '-p', '--path', required=True,
        help="destination area root path")
    args = parser.parse_args()
    return args

def execPatch(conf, base_path):
    """
    Patch files accroding to configuration
    @param conf: configuration dictionary
    @param base_path: project base path
    """
    # read vars
    patch_vars = conf.get('vars')
    if not patch_vars:
        print("FATAL: missing 'vars' in conf")
        exit(RC_FAIL)
    files = conf.get('files')
    # patch files
    for fname in files:
        try:
            full_name = os.path.join(base_path, fname)
            patchFile(full_name, patch_vars)
        except Exception as e:
            print("ERROR: patching {} exception: {}".format(full_name, e))

def patchFile(fpath, patch_vars):
    """
    Single file patch
    @param fpath: file full path
    @param patch_vars: dictionary of variables for patching
    """
    backup_fpath = "{}.back".format(fpath)
    # rename file to temp
    os.rename(fpath, backup_fpath)
    # open temp file for reading
    input_stream = open(backup_fpath, 'r')
    # open original file for write
    output_stream = open(fpath, 'w')
    # for each line perform substitute and write to output
    for line in input_stream:
        line = line.rstrip()
        # replace somehow
        line = patchLine(line, patch_vars)
        output_stream.write("{}\n".format(line))
        
    output_stream.close()
    input_stream.close()

def patchLine(line, patch_vars):
    """
    Patch given string using vars dictionary
    """
    for key, value in patch_vars.items():
        line = line.replace('{{' + key + '}}', value)
    return line

def readConf(conf_file_name):
    """
    Read conf-file
    @param conf_file_name: conf-file name
    @return: parsed structure from conf-file (exit on error)
    """
    if not os.path.exists(conf_file_name):
        print("FATAL: missing conf-file {}".format(conf_file_name))
        exit(RC_FAIL)
    if not conf_file_name.endswith('.json') and not conf_file_name.endswith('.yaml'):
        print("FATAL: unsupported conf-file format {}".format(conf_file_name))
        exit(RC_FAIL)
    try:
        with open(conf_file_name, "r") as stream:
            if conf_file_name.endswith('.json'):
                return json.load(stream)
            else:
                return yaml.safe_load(stream)
    except Exception as e:
        print("FATAL: conf-file read error:".format(e))

    exit(RC_FAIL)


if __name__ == "__main__":
   rc_main = main()
   exit(rc_main)