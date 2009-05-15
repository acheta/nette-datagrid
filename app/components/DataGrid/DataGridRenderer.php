<?php

require_once LIBS_DIR . '/Nette/Object.php';

require_once dirname(__FILE__) . '/IDataGridRenderer.php';



/**
 * Converts a data grid into the HTML output.
 *
 * @author     Roman Sklenář
 * @copyright  Copyright (c) 2009 Roman Sklenář
 * @example    http://nettephp.com/extras/datagrid
 * @package    Nette\Extras\DataGrid
 * @version    $Id$
 */
class DataGridRenderer extends Object implements IDataGridRenderer
{
	/** @var array  of HTML tags */
	public $wrappers = array(
		'form' => array(
			'container' => 'class=gridform',
			'errors' => TRUE,
		),
		
		'error' => array(
			'container' => 'ul class=error',
			'item' => 'li',
		),
		
		'grid' => array(
			'container' => 'table class=grid',
		),
		
		'row.header' => array(
			'container' => 'tr class=header',
			'cell' => array(
				'container' => 'th', // .checker, .action
			),
		),
		
		'row.filter' => array(
			'container' => 'tr class=filters',
			'cell' => array(
				'container' => 'td', // .action
			),
			'control' => array(
				'.input' => 'text',
				'.select' => 'select',
				'.submit' => 'button',
			),
		),
		
		'row.content' => array(
			'container' => 'tr', // .even, .selected
			'.even' => 'even',
			'cell' => array(
				'container' => 'td', // .checker, .action
			),
		),
		
		'row.footer' => array(
			'container' => 'tr class=footer',
			'cell' => array(
				'container' => 'td',
			),
		),
		
		'paginator' => array(
			'container' => 'span class=paginator',
			'button' => array(
				'container' => 'span', 
					// .paginator-prev, .paginator-next, 
					// .paginator-first, .paginator-last
			),
		),
		
		'operations' => array(
			'container' => 'span class=operations',
		),
		
		'info' => array(
			'container' => 'span class=grid-info',
		),
	);
	
	/** @var string */
	public $footerFormat = '%operations% | %paginator% | %info%';
	
	public $infoFormat = 'Displaying items %from% - %to% of %count%';
	
	/** @var string  template file*/
	public $file;

	/** @var DataGrid */
	protected $dataGrid;

	
	
	/**
	 * Data grid renderer constructor.
	 * @return void
	 */
	public function __construct()
	{
		$this->file = dirname(__FILE__) . '/grid.phtml';
	}


	/**
	 * Provides complete datagrid rendering.
	 * @param  DataGrid
	 * @param  string
	 * @return string
	 */
	public function render(DataGrid $dataGrid, $mode = NULL)
	{
		if ($this->dataGrid !== $dataGrid) {
			$this->dataGrid = $dataGrid;
		}
		
		if (!$dataGrid->dataSource instanceof DibiDataSource) {
			throw new InvalidArgumentException("Data source was not setted. You must set data source to data grid before rendering.");
		}
		
		if ($mode !== NULL) {
			return call_user_func_array(array($this, 'render' . $mode), NULL);
		}
		
		$template = $this->dataGrid->getTemplate();
		$template->setFile($this->file);
		$template->registerFilter('Nette\Templates\CurlyBracketsFilter::invoke');
		return $template->__toString();
	}


	/**
	 * Renders datagrid form begin.
	 * @return string
	 */
	public function renderBegin()
	{
		$form = $this->dataGrid->getForm(TRUE);
		foreach ($form->getControls() as $control) {
			$control->setOption('rendered', FALSE);
		}
		return $form->getElementPrototype()->startTag();
	}


	/**
	 * Renders datagrid form end.
	 * @return string
	 */
	public function renderEnd()
	{
		$form = $this->dataGrid->getForm(TRUE);
		return $form->getElementPrototype()->endTag() . "\n";
	}


	/**
	 * Renders validation errors (probably not necessary).
	 * @return string
	 */
	public function renderErrors()
	{
		$form = $this->dataGrid->getForm(TRUE);
		
		$errors = $form->getErrors();
		if (count($errors)) {
			$ul = $this->getWrapper('error container');
			$li = $this->getWrapper('error item');
			
			foreach ($errors as $error) {
				$item = clone $li;
				if ($error instanceof Html) {
					$item->add($error);
				} else {
					$item->setText($error);
				}
				$ul->add($item);
			}
			return "\n" . $ul->render(0);
		}
	}


	/**
	 * Renders data grid body.
	 * @return string
	 */
	public function renderBody()
	{
		$table = $this->getWrapper('grid container');
		
		// headers
		$table->add($this->generateHeaderRow());
		
		// filters
		if ($this->dataGrid->hasFilters()) {
			$table->add($this->generateFilterRow());
		}
		
		// rows
		$iterator = new SmartCachingIterator($this->dataGrid->getRows());
		foreach ($iterator as $data) {
			$row = $this->generateContentRow($data);
			$row->class[] = $iterator->isEven() ? $this->getValue('row.content .even') : '';
			$table->add($row);
		}
		
		// footer
		$table->add($this->generateFooterRow());
		
		return $table->render(0);
	}
	
	
	/**
	 * Renders data grid paginator.
	 * @return string
	 */
	public function renderPaginator()
	{
		$paginator = $this->dataGrid->paginator;
		if ($paginator->pageCount <= 1) return '';
		
		$container = $this->getWrapper('paginator container');
		$button = $this->getWrapper('paginator button container');
		$translator = $this->dataGrid->getTranslator();
		$a = Html::el('a')->class(DataGridColumn::$ajaxClass);
		
		// to-first button
		$first = clone $button;
		$first->class[] = 'paginator-first';
		$title = '« ' . $this->translate('First');
		if ($paginator->isFirst()) {
			$first->setText($title);
		} else {
			$link = clone $a->href($this->dataGrid->link('page', 1));
			$link->setText($title)->title($title);
			$first->add($link);
		}
		$container->add($first);
		
		// previous button
		$prev = clone $button;
		$prev->class[] = 'paginator-prev';
		$title = '« ' . $this->translate('Previous');
		if ($paginator->isFirst()) {
			$prev->setText($title);
		} else {
			$link = clone $a->href($this->dataGrid->link('page', $paginator->page - 1));
			$link->setText($title)->title($title);
			$prev->add($link);
		}
		$container->add($prev);
		
		// page input
		$form = $this->dataGrid->getForm(TRUE);
		$format = $this->translate('%label% %input% of %count%');
		$html = str_replace(
			array('%label%', '%input%', '%count%'),
			array($form['page']->label, $form['page']->control, $paginator->pageCount),
			$format
		);
		$container->add(Html::el()->setHtml($html));
		$container->add($form['pageSubmit']->control);
		
		// next button
		$next = clone $button;
		$next->class[] = 'paginator-next';
		$title = $this->translate('Next') . ' »';
		if ($paginator->isLast()) {			
			$next->setText($title);
		} else {
			$link = clone $a->href($this->dataGrid->link('page', $paginator->page + 1));
			$link->setText($title)->title($title);
			$next->add($link);
		}
		$container->add($next);
		
		// to-last button
		$last = clone $button;
		$last->class[] = 'paginator-last';
		$title = $this->translate('Last') . ' »';
		if ($paginator->isLast()) {			
			$last->setText($title);
		} else {
			$link = clone $a->href($this->dataGrid->link('page', $paginator->pageCount));
			$link->setText($title)->title($title);
			$last->add($link);
		}
		$container->add($last);
		
		unset($first, $prev, $next, $last, $button, $paginator, $link, $a, $form);		
		return $container->render();
	}
	
	
	/**
	 * Renders data grid operation controls.
	 * @return string
	 */
	public function renderOperations()
	{
		if (!$this->dataGrid->hasOperations()) return '';		
		
		$container = $this->getWrapper('operations container');
		$form = $this->dataGrid->getForm(TRUE);		
		$container->add($form['operations']->label);
		$container->add($form['operations']->control);
		$container->add($form['operationSubmit']->control);
		
		return $container->render();
	}
	
	
	/**
	 * Renders info about data grid.
	 * @return string
	 */
	public function renderInfo()
	{
		$container = $this->getWrapper('info container');
		$paginator = $this->dataGrid->paginator;
		
		$this->infoFormat = $this->translate($this->infoFormat);
		$html = str_replace(
			array(
				'%from%',
				'%to%',
				'%count%',
			),
			array(
				$paginator->itemCount != 0 ? $paginator->offset + 1 : $paginator->offset,
				$paginator->offset + $paginator->length,
				$paginator->itemCount,
			),
			$this->infoFormat
		);
		
		$container->setHtml($html);		
		return $container->render();
	}


	/**
	 * Renders datagrid headrer.
	 * @return Html
	 */
	protected function generateHeaderRow()
	{
		$row = $this->getWrapper('row.header container');
		
		// checker
		if ($this->dataGrid->hasOperations()) {
			$cell = $this->getWrapper('row.header cell container')->class('checker');
			
			if ($this->dataGrid->hasFilters()) {
				$cell->rowspan(2);
			}
			$row->add($cell);
		}
		
		// headers
		foreach ($this->dataGrid->getColumns() as $column) {
			$value = $text = $column->caption;
			
			if ($column->isOrderable()) {
				$i = 1;
				parse_str($this->dataGrid->order, $list);
				foreach ($list as $field => $dir) {
					$list[$field] = array($dir, $i++);
				}
				
				$class = DataGridColumn::$ajaxClass;
				if (isset($list[$column->getName()])) {
					$class .= ' ' . ($list[$column->getName()][0] === 'a' ? 'asc' : 'desc');
				}
				
				if (count($list) > 1 && isset($list[$column->getName()])) {
					$text .= '&nbsp;<span>' . $list[$column->getName()][1] . '</span>';
				}				
				
				$value = (string) Html::el('a')->href($column->getLink())->class($class)->setHtml($text);
			}
			
			$cell = $this->getWrapper('row.header cell container')->setHtml($value);
			$cell->attrs = $column->getHeaderPrototype()->attrs;
			if ($column instanceof ActionColumn) $cell->class[] = 'actions';
			
			$row->add($cell);
		}
		
		return $row;
	}


	/**
	 * Renders datagrid filter.
	 * @return Html
	 */
	protected function generateFilterRow()
	{
		$row = $this->getWrapper('row.filter container');
		$form = $this->dataGrid->getForm(TRUE);
		
		foreach ($this->dataGrid->getColumns() as $column) {
			$cell = $this->getWrapper('row.filter cell container');
			
			// TODO: set on filters too?
			$cell->attrs = $column->getCellPrototype()->attrs;
			
			if ($column instanceof ActionColumn) {
				$control = $form['filterSubmit']->control;
				$control->class[] = $this->getValue('row.filter control .submit');
				$value = (string) $control;
				$cell->class[] = 'actions';
				
			} else {
				if ($column->hasFilter()) {
					$filter = $column->getFilter();
					if ($filter instanceof SelectboxFilter) {
						$class = $this->getValue('row.filter control .select');
					} else {
						$class = $this->getValue('row.filter control .input');
					}
					$control = $filter->getFormControl()->control;
					$control->class[] = $class;
					$value = (string) $control;
				} else {
					$value = '&nbsp;';
				}
			}
			
			$cell->setHtml($value);
			$row->add($cell);
		}
		return $row;
	}


	/**
	 * Renders datagrid row content.
	 * @param  DibiRow data
	 * @return Html
	 */
	protected function generateContentRow($data)
	{
		$form = $this->dataGrid->getForm(TRUE);
		$row = $this->getWrapper('row.content container');
		
		if ($this->dataGrid->hasOperations() || $this->dataGrid->hasActions()) {
			$primary = $this->dataGrid->getKeyName();
			if (!array_key_exists($primary, $data)) {
				throw new InvalidArgumentException("Invalid name of key for group operations or actions. Column '" . $primary . "' does not exist in data source.");
			}
		}
		
		// checker
		if ($this->dataGrid->hasOperations()) {
			$value = $form['checker'][$data[$primary]]->getControl();
			$cell = $this->getWrapper('row.content cell container')->setHtml((string)$value)->class('checker');
			$row->add($cell);
		}
		
		// content
		foreach ($this->dataGrid->getColumns() as $column) {
			$cell = $this->getWrapper('row.content cell container');
			$cell->attrs = $column->getCellPrototype()->attrs;
			
			if ($column instanceof ActionColumn) {
				$value = '';
				foreach ($this->dataGrid->getActions() as $action) {
					$html = $action->getHtml();
					$html->title($this->translate($html->title));
					$action->generateLink(array($primary => $data[$primary]));
					$value .= $html->render() . ' ';
				}
				$cell->class[] = 'actions';
				
			} else {
				$value = $column->formatContent($data[$column->getName()]);
			}
			
			$cell->setHtml((string)$value);
			$row->add($cell);
		}
		unset($form, $primary, $cell, $value, $action);
		return $row;
	}


	/**
	 * Renders datagrid footer.
	 * @return Html
	 */
	protected function generateFooterRow()
	{
		$form = $this->dataGrid->getForm(TRUE);
		$paginator = $this->dataGrid->paginator;
		$row = $this->getWrapper('row.footer container');
		
		$count = count($this->dataGrid->getColumns()->getInnerIterator());
		if ($this->dataGrid->hasOperations()) $count++;
		
		$cell = $this->getWrapper('row.footer cell container');
		$cell->colspan($count);
		
		$this->footerFormat = $this->translate($this->footerFormat);
		$html = str_replace(
			array(
				'%operations%',
				'%paginator%',
				'%info%',
			),
			array(
				$this->renderOperations(),
				$this->renderPaginator(),
				$this->renderInfo(),
			),
			$this->footerFormat
		);
		$html = trim($html, ' | ');
		$cell->setHtml($html);
		$row->add($cell);
		
		return $row;
	}
	
	
	/**
	 * @param  string
	 * @return Html
	 */
	protected function getWrapper($name)
	{
		$data = $this->getValue($name);
		return $data instanceof Html ? clone $data : Html::el($data);
	}


	/**
	 * @param  string
	 * @return string
	 */
	protected function getValue($name)
	{
		$name = explode(' ', $name);
		if (count($name) == 3) {
			$data = & $this->wrappers[$name[0]][$name[1]][$name[2]];
		} else {
			$data = & $this->wrappers[$name[0]][$name[1]];
		}
		return $data;
	}


	/**
	 * Returns DataGrid.
	 * @return DataGrid
	 */
	public function getDataGrid()
	{
		return $this->dataGrid;
	}
	
	
	/********************* translator *********************/


	/**
	 * Returns translate adapter.
	 * @return ITranslator|NULL
	 */
	final public function getTranslator()
	{
		return $this->getDataGrid()->getTranslator();
	}


	/**
	 * Returns translated string.
	 * @param  string
	 * @return string
	 */
	public function translate($s)
	{
		$translator = $this->getTranslator();
		return $translator === NULL ? $s : $translator->translate($s);
	}
}