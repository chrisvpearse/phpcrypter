<?php

namespace Crypter\Console\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'encrypt')]
class Encrypt extends Command
{
    protected function configure()
    {
        $this->addArgument('payload', InputArgument::REQUIRED);
        $this->addArgument('path', InputArgument::REQUIRED | InputArgument::IS_ARRAY);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $payload = $input->getArgument('payload');
        $path = $input->getArgument('path');

        $cipherAlgo = 'AES-256-CBC';

        $payload = base64_decode($payload);

        if (! $payload || substr_count($payload, ',') != 2) {
            $output->writeln('<error>The payload format is invalid</error>');

            return Command::FAILURE;
        }

        [$name, $key] = explode(',', $payload, 3);

        $key = base64_decode($key);

        if (! $key || strlen($key) != openssl_cipher_key_length($cipherAlgo)) {
            $output->writeln('<error>The key within the payload is invalid</error>');

            return Command::FAILURE;
        }

        foreach ($path as $p) {
            $p = getcwd().'/'.$p;

            if (is_file($p)) {
                $p = new SplFileInfo($p);

                $this->encrypt($output, $p, $name, $cipherAlgo, $key);
            } elseif (is_dir($p)) {
                $iterator = new RegexIterator(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $p,
                            RecursiveDirectoryIterator::SKIP_DOTS
                        ),
                        RecursiveIteratorIterator::CHILD_FIRST
                    ),
                    '/\.php$/'
                );

                foreach ($iterator as $item) {
                    $this->encrypt($output, $item, $name, $cipherAlgo, $key);
                }
            }
        }

        return Command::SUCCESS;
    }

    private function encrypt($output, $file, $name, $cipherAlgo, $key)
    {
        $contents = file_get_contents($file->getPathname());

        if (! empty($contents)) {
            $sig = '<?php // @'.$name;

            if (substr($contents, 0, strlen($sig)) == $sig) {
                $output->writeln('<comment>Already Encrypted.</comment> '.$file->getPathname());
            } else {
                $iv = random_bytes(openssl_cipher_iv_length($cipherAlgo));

                $encrypted = openssl_encrypt(
                    $contents,
                    $cipherAlgo,
                    $key,
                    0,
                    $iv
                );

                $version = '0.1.0';

                $encoded = base64_encode($version.','.base64_encode($iv).','.$encrypted);

                $php = <<<PHP
                {$sig}
                if (! extension_loaded('{$name}')) exit('The "{$name}" extension is not loaded');
                #{$encoded}
                PHP;

                if (file_put_contents($file->getPathname(), $php)) {
                    $output->writeln('<info>Encrypted!</info> '.$file->getPathname());
                }
            }
        }
    }
}
