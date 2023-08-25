PHP_ARG_ENABLE(skeleton, whether to enable skeleton support,
[  --enable-skeleton        Enable skeleton support])

if test "$PHP_SKELETON" != "no"; then
    PHP_NEW_EXTENSION(skeleton, skeleton.c, $ext_shared)
fi
