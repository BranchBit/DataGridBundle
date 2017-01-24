<?php
namespace BBIT\DataGridBundle\Service;

use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;


class DataGridService
{
    /**
     * @var TwigEngine
     */
    private $twig;

    private $fields;
    /**
     * @var QueryBuilder
     */
    private $qb;
    private $pagedData;
    /**
     * @var PaginatorInterface
     */
    private $paginator;
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * DataGridService constructor.
     * @param $twig
     * @param PaginatorInterface $paginator
     * @param RequestStack $request
     */
    public function __construct(TwigEngine $twig, PaginatorInterface $paginator, RequestStack $request)
    {
        $this->twig = $twig;
        $this->fields = [];
        $this->paginator = $paginator;
        $this->requestStack = $request;
    }

    public function addField($fieldName, $type = 'string', $options = [])
    {
        $this->fields[] = ['fieldName' => $fieldName, 'type' => $type, 'options' => $options];
    }

    public function setQb($qb)
    {
        $this->qb = $qb;




    }

    public function addFiltersToQb()
    {
        foreach ($this->fields as $field) {
            if (isset($field['options'], $field['options']['filterable'])) {
                $filterParam = $this->requestStack->getCurrentRequest()->query->get('filter');
                $this->qb->andWhere('x.'.$field['fieldName'].' LIKE :'.$field['fieldName'])->setParameter($field['fieldName'], '%'.$filterParam[$field['fieldName']].'%');
            }
        }

        $this->pagedData = $this->paginator->paginate(
            $this->qb->getQuery(), /* query NOT result */
            $this->requestStack->getCurrentRequest()->query->getInt('page', 1)/*page number*/,
            2/*limit per page*/
        );

    }

    public function doCallbacks()
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($this->fields as $field) {
            if (isset($field['options'], $field['options']['callback'])) {
                foreach ($this->pagedData->getItems() as $item) {
                    $oldValue = $accessor->getValue($item, $field['fieldName']);
                    $callback = $field['options']['callback'];
                    $accessor->setValue($item, $field['fieldName'], $callback($oldValue));
                }
            }
        }
    }

    public function render()
    {
        $this->addFiltersToQb();
        $this->doCallbacks();
        return $this->twig->render('BBITDataGridBundle:Default:grid.html.twig', [
            'fields' => $this->fields,
            'data' => $this->pagedData
            ]

        );
    }
}