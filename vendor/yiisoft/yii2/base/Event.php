<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

/**
 * Event is the base class for all event classes.
 *
 * It encapsulates the parameters associated with an event.
 * The [[sender]] property describes who raises the event.
 * And the [[handled]] property indicates if the event is handled.
 * If an event handler sets [[handled]] to be `true`, the rest of the
 * uninvoked handlers will no longer be called to handle the event.
 *
 * Additionally, when attaching an event handler, extra data may be passed
 * and be available via the [[data]] property when the event handler is invoked.
 *
 * For more details and usage information on Event, see the [guide article on events](guide:concept-events).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Event extends Object
{
    /**
     * @var string the event name. This property is set by [[Component::trigger()]] and [[trigger()]].
     * Event handlers may use this property to check what event it is handling.
     * 事件名称
     */
    public $name;
    /**
     * @var object the sender of this event. If not set, this property will be
     * set as the object whose `trigger()` method is called.
     * This property may also be a `null` when this event is a
     * class-level event which is triggered in a static context.
     * 谁发起的事件
     */
    public $sender;
    /**
     * @var bool whether the event is handled. Defaults to `false`.
     * When a handler sets this to be `true`, the event processing will stop and
     * ignore the rest of the uninvoked event handlers.
     * 同名的事件会保留成数组
     * 这个参数用来控制下一个同名事件是否执行
     */
    public $handled = false;
    /**
     * @var mixed the data that is passed to [[Component::on()]] when attaching an event handler.
     * Note that this varies according to which event handler is currently executing.
     * 可以用来传递参数
     */
    public $data;

    /**
     * @var array contains all globally registered event handlers.
     * 保存的是类级别的事件
     * array(
     *  '事件名' => array(
     *              '类名1' => array(0=>array('回调函数','参数),1=>array('回调函数','参数))
     *              '类名2' => array(0=>array('回调函数','参数),1=>array('回调函数','参数))
     *              )
     *  '事件名' => array(
     *              '类名1' => array(0=>array('回调函数','参数),1=>array('回调函数','参数))
     *              '类名2' => array(0=>array('回调函数','参数),1=>array('回调函数','参数))
     *              )
     * )
     * 根据事件名key value是数组
     */
    private static $_events = [];


    /**
     * Attaches an event handler to a class-level event.
     *
     * When a class-level event is triggered, event handlers attached
     * to that class and all parent classes will be invoked.
     *
     * For example, the following code attaches an event handler to `ActiveRecord`'s
     * `afterInsert` event:
     *
     * ```php
     * Event::on(ActiveRecord::className(), ActiveRecord::EVENT_AFTER_INSERT, function ($event) {
     *     Yii::trace(get_class($event->sender) . ' is inserted.');
     * });
     * ```
     *
     * The handler will be invoked for EVERY successful ActiveRecord insertion.
     *
     * For more details about how to declare an event handler, please refer to [[Component::on()]].
     *
     * @param string $class the fully qualified class name to which the event handler needs to attach.
     * @param string $name the event name.
     * @param callable $handler the event handler.
     * @param mixed $data the data to be passed to the event handler when the event is triggered.
     * When the event handler is invoked, this data can be accessed via [[Event::data]].
     * @param bool $append whether to append new event handler to the end of the existing
     * handler list. If `false`, the new handler will be inserted at the beginning of the existing
     * handler list.
     * @see off()
     * 添加事件
     *  $class 需要带命名空间 get_called_class
     */
    public static function on($class, $name, $handler, $data = null, $append = true)
    {
        $class = ltrim($class, '\\');
        if ($append || empty(self::$_events[$name][$class])) {
            self::$_events[$name][$class][] = [$handler, $data];
        } else {
            array_unshift(self::$_events[$name][$class], [$handler, $data]);
        }
    }

    /**
     * Detaches an event handler from a class-level event.
     *
     * This method is the opposite of [[on()]].
     *
     * @param string $class the fully qualified class name from which the event handler needs to be detached.
     * @param string $name the event name.
     * @param callable $handler the event handler to be removed.
     * If it is `null`, all handlers attached to the named event will be removed.
     * @return bool whether a handler is found and detached.
     * @see on()
     * 删除事件
     */
    public static function off($class, $name, $handler = null)
    {
        $class = ltrim($class, '\\');
        if (empty(self::$_events[$name][$class])) {
            return false;
        }
        if ($handler === null) {
            unset(self::$_events[$name][$class]);
            return true;
        }

        $removed = false;
        foreach (self::$_events[$name][$class] as $i => $event) {
            if ($event[0] === $handler) {
                unset(self::$_events[$name][$class][$i]);
                $removed = true;
            }
        }
        if ($removed) {
            self::$_events[$name][$class] = array_values(self::$_events[$name][$class]);
        }
        return $removed;
    }

    /**
     * Detaches all registered class-level event handlers.
     * @see on()
     * @see off()
     * @since 2.0.10
     * 删掉全部的类事件
     */
    public static function offAll()
    {
        self::$_events = [];
    }

    /**
     * Returns a value indicating whether there is any handler attached to the specified class-level event.
     * Note that this method will also check all parent classes to see if there is any handler attached
     * to the named event.
     * @param string|object $class the object or the fully qualified class name specifying the class-level event.
     * @param string $name the event name.
     * @return bool whether there is any handler attached to the event.
     */
    public static function hasHandlers($class, $name)
    {
        if (empty(self::$_events[$name])) {
            return false;
        }
        if (is_object($class)) {
            $class = get_class($class);
        } else {
            $class = ltrim($class, '\\');
        }

        $classes = array_merge(
            [$class],
            class_parents($class, true),
            class_implements($class, true)
        );

        foreach ($classes as $class) {
            if (!empty(self::$_events[$name][$class])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Triggers a class-level event.
     * This method will cause invocation of event handlers that are attached to the named event
     * for the specified class and all its parent classes.
     * @param string|object $class the object or the fully qualified class name specifying the class-level event.
     * @param string $name the event name.
     * @param Event $event the event parameter. If not set, a default [[Event]] object will be created.
     * 触发事件  会把父对象的事件一起触发
     * interface test{}
     * class foo implements test { }
     * class bar extends foo {}
     * print_r(class_parents('bar'));       Array([foo] => foo)
     * print_r(class_implements('bar'));    Array([test] => test)
     */
    public static function trigger($class, $name, $event = null)
    {
        if (empty(self::$_events[$name])) {
            return;
        }
        if ($event === null) {
            $event = new static;
        }
        $event->handled = false;
        $event->name = $name;

        if (is_object($class)) {
            if ($event->sender === null) {
                $event->sender = $class;
            }
            $class = get_class($class);
        } else {
            $class = ltrim($class, '\\');
        }

        $classes = array_merge(
            [$class],
            class_parents($class, true),
            class_implements($class, true)
        );

        foreach ($classes as $class) {
            if (empty(self::$_events[$name][$class])) {
                continue;
            }
            
            foreach (self::$_events[$name][$class] as $handler) {
                $event->data = $handler[1];
                call_user_func($handler[0], $event);
                if ($event->handled) {
                    return;
                }
            }
        }
    }
}
