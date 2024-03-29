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

#[AsCommand(name: 'decrypt')]
class Decrypt extends Command
{
    private $cipherAlgo = 'aes-256-cbc';

    protected function configure()
    {
        $this->addArgument('payload', InputArgument::REQUIRED);
        $this->addArgument('path', InputArgument::REQUIRED | InputArgument::IS_ARRAY);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $payload = $input->getArgument('payload');
        $path = $input->getArgument('path');

        $payload = base64_decode($payload);

        if (! $payload || substr_count($payload, ',') != 2) {
            $output->writeln('<error>The payload format is invalid</error>');

            return Command::FAILURE;
        }

        [$name, $key] = explode(',', $payload, 3);

        $key = base64_decode($key);

        if (! $key || strlen($key) != openssl_cipher_key_length($this->cipherAlgo)) {
            $output->writeln('<error>The key within the payload is invalid</error>');

            return Command::FAILURE;
        }

        foreach ($path as $p) {
            $p = getcwd().'/'.$p;

            if (is_file($p)) {
                $p = new SplFileInfo($p);

                $this->decrypt($output, $p, $name, $key);
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
                    $this->decrypt($output, $item, $name, $key);
                }
            }
        }

        return Command::SUCCESS;
    }

    private function decrypt($output, $file, $name, $key)
    {
        $contents = file_get_contents($file->getPathname());

        if (! empty($contents)) {
            $sig = '<?php // @'.$name;

            if (substr($contents, 0, strlen($sig)) == $sig) {
                [, $encoded] = explode('#', $contents);

                [, $iv, $encrypted] = explode(',', base64_decode($encoded));

                $decrypted = openssl_decrypt(
                    $encrypted,
                    $this->cipherAlgo,
                    $key,
                    0,
                    base64_decode($iv)
                );

                if (file_put_contents($file->getPathname(), $decrypted)) {
                    $output->writeln('<info>Decrypted!</info> '.$file->getPathname());
                }
            }
        }
    }
}
