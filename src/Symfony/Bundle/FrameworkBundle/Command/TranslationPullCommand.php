<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Catalogue\TargetOperation;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
final class TranslationPullCommand extends Command
{
    use TranslationTrait;

    protected static $defaultName = 'translation:pull';

    private $providers;
    private $writer;
    private $reader;
    private $defaultLocale;
    private $transPaths;
    private $enabledLocales;

    public function __construct(TranslationProviderCollection $providers, TranslationWriterInterface $writer, TranslationReaderInterface $reader, string $defaultLocale, string $defaultTransPath = null, array $transPaths = [], array $enabledLocales = [])
    {
        $this->providers = $providers;
        $this->writer = $writer;
        $this->reader = $reader;
        $this->defaultLocale = $defaultLocale;
        $this->transPaths = $transPaths;
        $this->enabledLocales = $enabledLocales;

        if (null !== $defaultTransPath) {
            $this->transPaths[] = $defaultTransPath;
        }

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $keys = $this->providers->keys();
        $defaultProvider = 1 === \count($keys) ? $keys[0] : null;

        $this
            ->setDefinition([
                new InputArgument('provider', null !== $defaultProvider ? InputArgument::OPTIONAL : InputArgument::REQUIRED, 'The provider to pull translations from.', $defaultProvider),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Override existing translations with provider ones (it will delete not synchronized messages).'),
                new InputOption('domains', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the domains to pull.'),
                new InputOption('locales', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the locales to pull.'),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format.', 'xlf'),
                new InputOption('xliff-version', null, InputOption::VALUE_OPTIONAL, 'Override the default xliff version.', '1.2'),
            ])
            ->setDescription('Pull translations from a given provider.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> pull translations from the given provider. Only
new translations are pulled, existing ones are not overwritten.

You can overwrite existing translations (and remove the missing ones on local side):

  <info>php %command.full_name% --force provider</info>

Full example:

  <info>php %command.full_name% provider --force --domains=messages,validators --locales=en</info>

This command will pull all translations linked to domains messages and validators
for the locale en. Local translations for the specified domains and locale will
be erased if they're not present on the provider and overwritten if it's the
case. Local translations for others domains and locales will be ignored.
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $provider = $this->providers->get($input->getArgument('provider'));
        $locales = $input->getOption('locales') ?: $this->enabledLocales;
        $domains = $input->getOption('domains');
        $force = $input->getOption('force');

        $writeOptions = [
            'path' => end($this->transPaths),
            'xliff_version' => $input->getOption('xliff-version'),
        ];

        if (!$domains) {
            $domains = $provider->getDomains();
        }

        $providerTranslations = $provider->read($domains, $locales);

        if ($force) {
            foreach ($providerTranslations->getCatalogues() as $catalogue) {
                $operation = new TargetOperation((new MessageCatalogue($catalogue->getLocale())), $catalogue);
                $operation->moveMessagesToIntlDomainsIfPossible();
                $this->writer->write($operation->getResult(), $input->getOption('output-format'), $writeOptions);
            }

            $io->success(sprintf(
                'Local translations has been updated from %s (for [%s] locale(s), and [%s] domain(s)).',
                $provider->getName(),
                implode(', ', $locales),
                implode(', ', $domains)
            ));

            return 0;
        }

        $localTranslations = $this->readLocalTranslations($locales, $domains, $this->transPaths);
        $translationsToWrite = $providerTranslations->diff($localTranslations);

        foreach ($translationsToWrite->getCatalogues() as $catalogue) {
            $this->writer->write($catalogue, $input->getOption('output-format'), $writeOptions);
        }

        $io->success(sprintf(
            'New translations from %s has been written locally (for [%s] locale(s), and [%s] domain(s)).',
            $provider->getName(),
            implode(', ', $locales),
            implode(', ', $domains)
        ));

        return 0;
    }
}
