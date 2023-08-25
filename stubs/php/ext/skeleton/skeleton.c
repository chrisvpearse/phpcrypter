#include "php.h"
#include "ext/standard/info.h"
#include "ext/standard/file.h"
#include "ext/standard/base64.h"
#include "ext/openssl/php_openssl.h"
#include "skeleton.h"

ZEND_DECLARE_MODULE_GLOBALS(skeleton)

static zend_op_array* (*old_compile_file)(zend_file_handle *file_handle, int type);
static zend_op_array* new_compile_file(zend_file_handle *file_handle, int type)
{
    if (PHP_VERSION_ID < 80200 || ! SKELETON_G(decrypt)) {
        return old_compile_file(file_handle, type);
    }

    // @char

    do {
        FILE *fp;

        fp = fopen(ZSTR_VAL(file_handle->filename), "rb");

        if (! fp) {
            break;
        }

        char sig[] = SKELETON_SIG;
        size_t sig_length = strlen(sig);

        char *sig_buffer = (char *)emalloc(sig_length);
        fread(sig_buffer, sizeof(char), sig_length, fp);

        if (memcmp(sig_buffer, sig, sig_length) != 0) {
            fclose(fp);

            efree(sig_buffer);

            break;
        }

        efree(sig_buffer);

        fseek(fp, 0, SEEK_END);
        long file_size = ftell(fp);
        fseek(fp, 0, SEEK_SET);

        char *file_contents = (char *)emalloc(file_size);
        fread(file_contents, sizeof(char), file_size, fp);

        fclose(fp);

        strtok(file_contents, "#");
        char *encoded_data = strtok(NULL, "#");

        efree(file_contents);

        if (! encoded_data) {
            break;
        }

        zend_string *tmp_encoded_data = zend_string_init(encoded_data, strlen(encoded_data), 0);
        zend_string *decoded_data = php_base64_decode_str(tmp_encoded_data);
        zend_string_release(tmp_encoded_data);

        if (! ZSTR_LEN(decoded_data)) {
            break;
        }

        char *skeleton_version = strtok(ZSTR_VAL(decoded_data), ",");
        char *encoded_iv = strtok(NULL, ",");
        char *encrypted_data = strtok(NULL, ",");

        zend_string_release(decoded_data);

        if (! skeleton_version || ! encoded_iv || ! encrypted_data) {
            break;
        }

        if (strcmp(skeleton_version, SKELETON_VERSION) != 0) {
            break;
        }

        zend_string *tmp_encoded_iv = zend_string_init(encoded_iv, strlen(encoded_iv), 0);
        zend_string *decoded_iv = php_base64_decode_str(tmp_encoded_iv);
        zend_string_release(tmp_encoded_iv);

        if (! ZSTR_LEN(decoded_iv)) {
            break;
        }

        char *iv = ZSTR_VAL(decoded_iv);

        size_t key_xor_length = SKELETON_KEY_LENGTH;
        size_t xor_key_length = SKELETON_KEY_LENGTH;

        char key_xor[key_xor_length];
        char xor_key[xor_key_length];

        // @memcpy

        char key[key_xor_length];

        for (size_t i = 0; i < key_xor_length; i++) {
            key[i] = key_xor[i] ^ xor_key[i % sizeof(xor_key)];
        }

        char *cipher_algo = SKELETON_CIPHER_ALGO;

        zend_string *decrypted_data = php_openssl_decrypt(
            encrypted_data, strlen(encrypted_data),
            cipher_algo, strlen(cipher_algo),
            key, strlen(key),
            0,
            iv, strlen(iv),
            NULL, 0,
            NULL, 0
        );

        if (! ZSTR_LEN(decrypted_data)) {
            break;
        }

        size_t decrypted_data_length = ZSTR_LEN(decrypted_data);
        char *new_buffer = estrndup(ZSTR_VAL(decrypted_data), decrypted_data_length);

        zend_string_release(decrypted_data);

        char *tmp_buffer = NULL;
        size_t tmp_length = 0;

        if (zend_stream_fixup(file_handle, &tmp_buffer, &tmp_length) == FAILURE) {
            break;
        }

        if (file_handle->buf != NULL) {
            efree(file_handle->buf);
        }

        file_handle->buf = new_buffer;
        file_handle->len = decrypted_data_length;
    } while (0);

    return old_compile_file(file_handle, type);
}

static void php_skeleton_init_globals(zend_skeleton_globals *skeleton_globals) {
    skeleton_globals->decrypt = 0;
}

PHP_INI_BEGIN()
    STD_PHP_INI_BOOLEAN("skeleton.decrypt", "0", PHP_INI_ALL, OnUpdateBool, decrypt, zend_skeleton_globals, skeleton_globals)
PHP_INI_END()

PHP_MINIT_FUNCTION(skeleton)
{
    ZEND_INIT_MODULE_GLOBALS(skeleton, php_skeleton_init_globals, NULL);
    REGISTER_INI_ENTRIES();

    old_compile_file = zend_compile_file;
    zend_compile_file = new_compile_file;

    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(skeleton)
{
    zend_compile_file = old_compile_file;

    return SUCCESS;
}

PHP_RINIT_FUNCTION(skeleton)
{
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(skeleton)
{
    return SUCCESS;
}

PHP_MINFO_FUNCTION(skeleton)
{
    php_info_print_table_start();
    php_info_print_table_row(2, "skeleton", "enabled");
    php_info_print_table_row(2, "version", SKELETON_VERSION);
    php_info_print_table_end();
}

zend_function_entry skeleton_functions[] = {
    ZEND_FE_END
};

zend_module_entry skeleton_module_entry = {
    STANDARD_MODULE_HEADER,
    "skeleton",
    skeleton_functions,
    PHP_MINIT(skeleton),
    PHP_MSHUTDOWN(skeleton),
    PHP_RINIT(skeleton),
    PHP_RSHUTDOWN(skeleton),
    PHP_MINFO(skeleton),
    SKELETON_VERSION,
    STANDARD_MODULE_PROPERTIES
};

ZEND_GET_MODULE(skeleton)
