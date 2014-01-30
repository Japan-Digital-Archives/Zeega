<?php

/*
* This file is part of Zeega.
*
* (c) Zeega <info@zeega.org>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Zeega\IngestionBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use Zeega\CoreBundle\Helpers\ResponseHelper;
use Zeega\DataBundle\Entity\Item;
use Zeega\DataBundle\Entity\Tag;

/**
 * create tag mappings
 *
 */
class CreateTagsCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this->setName('zeega:createtags')
             ->setDescription('Create the tag mappings')
             ->setHelp("Help");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getEntityManager();
        $tagRepo = $em->getRepository('ZeegaDataBundle:Tag');
        $itemRepo = $em->getRepository('ZeegaDataBundle:Item');

        $count = $em->createQueryBuilder()
                    ->select('count(item.id)')
                    ->from('ZeegaDataBundle:Item', 'item')
                    ->getQuery()
                    ->getSingleScalarResult();

        for($i = 0; $i < $count; $i+=1000) {
            $query = $itemRepo->createQueryBuilder('i')
                ->select('i')
                ->setFirstResult($i)
                ->setMaxResults(1000)
                ->getQuery();

            $items = $query->getResult();
            foreach ($items as &$item) {
                $tq = $tagRepo->createQueryBuilder('t')
                        ->where('t.name IN (\'' . implode("','", $item->getTags()) . '\')')
                        ->getQuery();

                $tagList = $tq->getResult();
                $tagDict = array();
                foreach($tagList as &$tag) {
                    $tagDict[$tag->getName()] = $tag;
                }

                $tagArr = $item->getTags();
                foreach($tagArr as &$tagName) {
                    $tag = null;
                    if(array_key_exists($tagName, $tagDict)) {
                        $tag = $tagDict[$tagName];
                    } else {
                        $tag = new Tag();
                        $tag->setName($tagName);
                        $tag->setDateCreated(new \DateTime("now"));
                        $tagDict[$tagName] = $tag;
                    }
                    $tag->removeItem($item);
                    $tag->addItem($item);
                    $em->persist($tag);
                }
            }

            $em->flush();

            $end = $i + 1000;
            $output->writeln("<info>Items $i to $end of $count completed</info>");
        }


        $output->writeln("<info>All $count item tags have been generated</info>");
    }
}
