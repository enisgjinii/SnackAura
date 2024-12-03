<?php
// includes/functions.php

/**
 * Fetch category-specific options along with their possible values.
 *
 * @param PDO $pdo
 * @param int $category_id
 * @return array
 */
function fetchCategoryOptions($pdo, $category_id) {
    $query = 'SELECT co.id AS option_id, co.name, co.label, co.type, co.is_required,
                     cov.id AS value_id, cov.value, cov.additional_price
              FROM category_options co
              LEFT JOIN category_option_values cov ON co.id = cov.category_option_id
              WHERE co.category_id = ?
              ORDER BY co.id, cov.id';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$category_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $options = [];
    foreach ($results as $row) {
        $option_id = $row['option_id'];
        if (!isset($options[$option_id])) {
            $options[$option_id] = [
                'id' => $option_id,
                'name' => $row['name'],
                'label' => $row['label'],
                'type' => $row['type'],
                'is_required' => $row['is_required'],
                'values' => []
            ];
        }
        if ($row['value_id']) {
            $options[$option_id]['values'][] = [
                'id' => $row['value_id'],
                'value' => $row['value'],
                'additional_price' => $row['additional_price']
            ];
        }
    }
    return $options;
}

/**
 * Fetch existing product category options for editing.
 *
 * @param PDO $pdo
 * @param int $product_id
 * @return array
 */
function fetchProductCategoryOptions($pdo, $product_id) {
    $query = 'SELECT pco.category_option_id, pco.option_value_id, pco.custom_value
              FROM product_category_options pco
              WHERE pco.product_id = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$product_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($options as $option) {
        $result[$option['category_option_id']] = [
            'option_value_id' => $option['option_value_id'],
            'custom_value' => $option['custom_value']
        ];
    }
    return $result;
}
?>
