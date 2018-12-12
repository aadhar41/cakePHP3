<?php
// src/Model/Table/ArticlesTable.php
namespace App\Model\Table;

use Cake\ORM\Table;

// the Text class
use Cake\Utility\Text;

use Cake\Validation\Validator;

use Cake\ORM\Query;


class ArticlesTable extends Table
{
    public function initialize(array $config)
    {
        $this->addBehavior('Timestamp');

        // Article has many tags
        $this->belongsToMany('Tags');
    }


	public function beforeSave($event, $entity, $options)
	{
	    if ($entity->isNew() && !$entity->slug) {
	        $sluggedTitle = Text::slug($entity->title);
	        // trim slug to maximum length defined in schema
	        $entity->slug = substr($sluggedTitle, 0, 191);
	    }

	    if ($entity->tag_string) {
	        $entity->tags = $this->_buildTags($entity->tag_string);
	    }

	}


	protected function _buildTags($tagString)
	{
	    // Trim tags
	    $newTags = array_map('trim', explode(',', $tagString));
	    // Remove all empty tags
	    $newTags = array_filter($newTags);
	    // Reduce duplicated tags
	    $newTags = array_unique($newTags);

	    $out = [];
	    $query = $this->Tags->find()
	        ->where(['Tags.title IN' => $newTags]);

	    // Remove existing tags from the list of new tags.
	    foreach ($query->extract('title') as $existing) {
	        $index = array_search($existing, $newTags);
	        if ($index !== false) {
	            unset($newTags[$index]);
	        }
	    }
	    // Add existing tags.
	    foreach ($query as $tag) {
	        $out[] = $tag;
	    }
	    // Add new tags.
	    foreach ($newTags as $tag) {
	        $out[] = $this->Tags->newEntity(['title' => $tag]);
	    }
	    return $out;
	}


	// Add the following method.
	public function validationDefault(Validator $validator)
	{
	    $validator
	        ->notEmpty('title')
	        ->minLength('title', 10)
	        ->maxLength('title', 255)

	        ->notEmpty('body')
	        ->minLength('body', 10);

	    return $validator;
	}


	// The $query argument is a query builder instance.
	// The $options array will contain the 'tags' option we passed
	// to find('tagged') in our controller action.
	public function findTagged(Query $query, array $options)
	{
	    $columns = [
	        'Articles.id', 'Articles.user_id', 'Articles.title',
	        'Articles.body', 'Articles.published', 'Articles.created',
	        'Articles.slug',
	    ];

	    $query = $query
	        ->select($columns)
	        ->distinct($columns);

	    if (empty($options['tags'])) {
	        // If there are no tags provided, find articles that have no tags.
	        $query->leftJoinWith('Tags')
	            ->where(['Tags.title IS' => null]);
	    } else {
	        // Find articles that have one or more of the provided tags.
	        $query->innerJoinWith('Tags')
	            ->where(['Tags.title IN' => $options['tags']]);
	    }

	    
	    return $query->group(['Articles.id']);

	}



	public function isAuthorized($user)
	{
	    $action = $this->request->getParam('action');
	    // The add and tags actions are always allowed to logged in users.
	    if (in_array($action, ['add', 'tags'])) {
	        return true;
	    }

	    // All other actions require a slug.
	    $slug = $this->request->getParam('pass.0');
	    if (!$slug) {
	        return false;
	    }

	    // Check that the article belongs to the current user.
	    $article = $this->Articles->findBySlug($slug)->first();

	    return $article->user_id === $user['id'];
	}





}