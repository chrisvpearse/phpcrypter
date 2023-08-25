<?php

namespace Crypter\Console\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'generate')]
class Generate extends Command
{
    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED);
        $this->addArgument('payload', InputArgument::OPTIONAL);
        $this->addOption('clean', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $payload = $input->getArgument('payload');
        $clean = $input->getOption('clean');

        if (! preg_match('/^[a-zA-Z]+$/', $name)) {
            $output->writeln('<error>The "name" must contain alpha characters only</error>');

            return Command::FAILURE;
        }

        $name = strtolower($name);

        $dir = '.phpcrypter/'.$name;
        $path = getcwd().'/'.$dir;

        if (is_dir($path)) {
            if ($clean) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $path,
                        RecursiveDirectoryIterator::SKIP_DOTS
                    ),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        if (! rmdir($item->getPathname())) {
                            $output->writeln('<error>Could not remove directory: '.$item->getPathname().'</error>');

                            return Command::FAILURE;
                        }
                    } else {
                        if (! unlink($item->getPathname())) {
                            $output->writeln('<error>Could not remove file: '.$item->getPathname().'</error>');

                            return Command::FAILURE;
                        }
                    }
                }
            } else {
                $output->writeln('<error>"'.$dir.'" already exists in the current working directory</error>');
                $output->writeln('<comment>To rebuild, please specify the "--clean" option</comment>');

                return Command::FAILURE;
            }
        } else {
            if (! mkdir($path, 0777, true)) {
                $output->writeln('<error>Could not make "'.$dir.'" in the current working directory</error>');

                return Command::FAILURE;
            }
        }

        $cipherAlgo = 'AES-256-CBC';

        if (is_null($payload)) {
            $key = random_bytes(openssl_cipher_key_length($cipherAlgo));
            $xorKey = random_bytes(openssl_cipher_key_length($cipherAlgo));
        } else {
            $payload = base64_decode($payload);

            if (! $payload || substr_count($payload, ',') != 2) {
                $output->writeln('<error>The payload format is invalid</error>');

                return Command::FAILURE;
            }

            [, $key, $xorKey] = explode(',', $payload, 3);

            $key = base64_decode($key);
            $xorKey = base64_decode($xorKey);

            if (! $key || strlen($key) != openssl_cipher_key_length($cipherAlgo)) {
                $output->writeln('<error>The key within the payload is invalid</error>');

                return Command::FAILURE;
            }

            if (! $xorKey || strlen($xorKey) != openssl_cipher_key_length($cipherAlgo)) {
                $output->writeln('<error>The XOR key within the payload is invalid</error>');

                return Command::FAILURE;
            }
        }

        $keyXorArr = [];
        $xorKeyArr = [];

        for ($i = 0; $i < strlen($key); $i++) {
            $byte = $key[$i] ^ $xorKey[$i % strlen($xorKey)];

            $keyXorArr[$i] = '0x'.bin2hex($byte);
        }

        foreach (str_split($xorKey) as $byte) {
            $xorKeyArr[] = '0x'.bin2hex($byte);
        }

        $char = [];
        $memcpy = [];

        foreach ($keyXorArr as $k => $byte) {
            $i = $k + 1;

            $char[] = 'unsigned char key_xor_'.$i.'[] = {'.$byte.'};';

            $memcpy[] = 'memcpy(key_xor + '.$k.', key_xor_'.$i.', sizeof(key_xor_'.$i.'));';
        }

        foreach ($xorKeyArr as $k => $byte) {
            $i = $k + 1;

            $char[] = 'unsigned char xor_key_'.$i.'[] = {'.$byte.'};';

            $memcpy[] = 'memcpy(xor_key + '.$k.', xor_key_'.$i.', sizeof(xor_key_'.$i.'));';
        }

        for ($i = 1; $i <= 32; $i++) {
            $j = $i + 32;

            $byte = '0x'.bin2hex(random_bytes(1));
            $char[] = 'unsigned char key_xor_'.$j.'[] = {'.$byte.'};';

            $byte = '0x'.bin2hex(random_bytes(1));
            $char[] = 'unsigned char xor_key_'.$j.'[] = {'.$byte.'};';
        }

        shuffle($char);

        $char = implode(' ', $char);
        $memcpy = implode(' ', $memcpy);

        $search = [
            'skeleton',
            'SKELETON',
            '// @char',
            '// @memcpy',
        ];

        $replace = [
            $name,
            strtoupper($name),
            $char,
            $memcpy,
        ];

        $phpStubs = __DIR__.'/../../../stubs/php';

        $extSkeleton = 'ext/skeleton';
        $extOpenssl = 'ext/openssl';

        $paths = [
            [
                'from' => $phpStubs.'/'.$extSkeleton.'/config.m4',
                'to' => $path.'/config.m4',
            ],
            [
                'from' => $phpStubs.'/'.$extSkeleton.'/config.w32',
                'to' => $path.'/config.w32',
            ],
            [
                'from' => $phpStubs.'/'.$extSkeleton.'/skeleton.c',
                'to' => $path.'/'.$name.'.c',
            ],
            [
                'from' => $phpStubs.'/'.$extSkeleton.'/skeleton.h',
                'to' => $path.'/'.$name.'.h',
            ],
            [
                'from' => $phpStubs.'/'.$extOpenssl.'/php_openssl.h',
                'to' => $path.'/'.$extOpenssl.'/php_openssl.h',
            ],
        ];

        if (! mkdir($path.'/'.$extOpenssl, 0777, true)) {
            $output->writeln('<error>Could not make directory: '.$path.'/'.$extOpenssl.'</error>');

            return Command::FAILURE;
        }

        foreach ($paths as $path) {
            if (is_file($path['from']) && ! file_put_contents($path['to'], str_replace($search, $replace, file_get_contents($path['from'])))) {
                $output->writeln('<error>Could not make file: '.$path['to'].'</error>');

                return Command::FAILURE;
            }
        }

        $payload = base64_encode(
            $name.','.base64_encode($key).','.base64_encode($xorKey)
        );

        $output->writeln('<info>Success!</info>');
        $output->writeln('Payload: <comment>'.$payload.'</comment>');

        return Command::SUCCESS;
    }
}
