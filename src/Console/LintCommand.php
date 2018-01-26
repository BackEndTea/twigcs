<?php

namespace Allocine\Twigcs\Console;

use Allocine\Twigcs\Ruleset\Official;
use Allocine\Twigcs\Ruleset\RulesetInterface;
use Allocine\Twigcs\Validator\Violation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class LintCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('lint')
            ->addArgument('path')
            ->addOption('severity', 's', InputOption::VALUE_REQUIRED, 'The maximum allowed error level.', 'warning')
            ->addOption('reporter', 'r', InputOption::VALUE_REQUIRED, 'The reporter to use.', 'console')
            ->addOption('ruleset', null, InputOption::VALUE_REQUIRED, 'Ruleset class to use', Official::class)
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $limit = $this->getSeverityLimit($input);

        $path = $input->getArgument('path');

        if (is_file($path)) {
            $files = [new \SplFileInfo($input->getArgument('path'))];
        } else {
            $finder = new Finder();
            $files = $finder->in($path)->name('*.twig');
        }

        $ruleset = $input->getOption('ruleset');

        if (!is_subclass_of($ruleset, RulesetInterface::class)) {
            throw new \InvalidArgumentException('Ruleset class must implement ' . RulesetInterface::class);
        }

        $ruleset = new $ruleset();

        foreach ($files as $file) {
            $container['validator']->check($ruleset, $container['twig']->tokenize(new \Twig_Source(
                file_get_contents($file->getRealPath()),
                $file->getRealPath(),
                str_replace(realpath($path), $path, $file->getRealPath())
            )));
        }

        $violations = $container['validator']->validate($ruleset);

        $container[sprintf('reporter.%s', $input->getOption('reporter'))]->report($output, $violations);

        foreach ($violations as $violation) {
            if ($violation->getSeverity() > $limit) {
                return 1;
            }
        }

        return 0;
    }

    private function getSeverityLimit(InputInterface $input)
    {
        switch ($input->getOption('severity')) {
            case 'ignore':
                return Violation::SEVERITY_IGNORE - 1;
            case 'info':
                return Violation::SEVERITY_INFO - 1;
            case 'warning':
                return Violation::SEVERITY_WARNING - 1;
            case 'error':
                return Violation::SEVERITY_ERROR - 1;
            default:
                throw new \InvalidArgumentException('Invalid severity limit provided.');
        }
    }
}
