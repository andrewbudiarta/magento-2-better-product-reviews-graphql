<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_BetterProductReviewsGraphQl
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

declare(strict_types=1);

namespace Mageplaza\BetterProductReviewsGraphQl\Model\Resolver;

use Exception;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mageplaza\BetterProductReviews\Model\ResourceModel\Review\CollectionFactory;

/**
 * Class CreateReview
 * @package Mageplaza\BetterProductReviewsGraphQl\Model\Resolver
 */
class CreateReview implements ResolverInterface
{
    /**
     * @var RatingFactory
     */
    protected $_rating;

    /**
     * @var ReviewFactory
     */
    protected $_review;

    /**
     * CreateReview constructor.
     *
     * @param RatingFactory $ratingFactory
     * @param ReviewFactory $reviewFactory
     */
    public function __construct(
        RatingFactory $ratingFactory,
        ReviewFactory $reviewFactory
    ) {
        $this->_rating = $ratingFactory;
        $this->_review                      = $reviewFactory;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     *
     * @return mixed|Value
     * @throws Exception
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($args['input']) || !is_array($args['input']) || empty($args['input'])) {
            throw new GraphQlInputException(__('"input" value should be specified'));
        }

        $data       = $args['input'];
        $productId  = $args['productId'];
        $storeId    = isset($data['store_id']) ? $data['store_id'] : 1;
        $customerId = isset($data['customer_id']) ? $data['customer_id'] : null;
        $avgValue   = isset($data['avg_value']) ? $data['avg_value'] : '5';
        $status     = isset($data['status_id']) ? $data['status_id'] : Review::STATUS_PENDING;
        $ratings    = $this->getRatingCollection($storeId);
        $object     = $this->_review->create()->setData($data);
        $object->unsetData('review_id');

        if ($object->validate()) {
            $object->setEntityId($object->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue($productId)
                ->setStatusId($status)
                ->setCustomerId($customerId)
                ->setStoreId($storeId)
                ->setStores([$storeId])
                ->save();
            foreach ($ratings as $ratingId => $rating) {
                foreach ($rating->getOptions() as $option) {
                    if ($option->getValue() === $avgValue) {
                        $this->_rating->create()
                            ->setRatingId($ratingId)
                            ->setReviewId($object->getId())
                            ->setCustomerId($customerId)
                            ->addOptionVote($option->getId(), $productId);
                    }
                }
            }
            $object->aggregate();

            return $object;
        }

        return [];
    }

    /**
     * @param $storeId
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRatingCollection($storeId)
    {
        return $this->_rating->create()->getResourceCollection()->addEntityFilter(
            'product'
        )->setPositionOrder()->addRatingPerStoreName(
            $storeId
        )->setStoreFilter(
            $storeId
        )->setActiveFilter(
            true
        )->load()->addOptionToItems();
    }
}
