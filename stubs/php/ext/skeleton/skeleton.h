#ifndef PHP_SKELETON_H
#define PHP_SKELETON_H

extern zend_module_entry skeleton_module_entry;
#define phpext_skeleton_ptr &skeleton_module_entry

#define SKELETON_VERSION "0.1.1"
#define SKELETON_SIG "<?php // @skeleton"

#define SKELETON_CIPHER_ALGO "AES-256-CBC"
#define SKELETON_KEY_LENGTH 32

ZEND_BEGIN_MODULE_GLOBALS(skeleton)
    zend_bool decrypt;
ZEND_END_MODULE_GLOBALS(skeleton)

#define SKELETON_G(v) (skeleton_globals.v)

#endif
