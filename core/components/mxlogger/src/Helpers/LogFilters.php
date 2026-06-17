<?php

declare(strict_types=1);

namespace MxLogger\Helpers;

use MODX\Revolution\modUser;
use MODX\Revolution\modX;

/**
 * Общий построитель условий фильтрации логов для грида.
 * Используется и в GetList (выборка), и в Clear (очистка), чтобы фильтры
 * очистки гарантированно совпадали с тем, что видно в гриде.
 *
 * Все условия задаются без алиаса таблицы — валидно и в SELECT, и в DELETE.
 * Значения экранируются quote(); тэги нормализованы до [a-z0-9].
 *
 * @package MxLogger\Helpers
 */
class LogFilters
{
    /**
     * Построить массив условий ($where) по свойствам процессора.
     * Формат совместим с xPDOQuery::where() и modX::removeCollection().
     *
     * @param array $p Свойства процессора (getProperties()).
     */
    public static function build(modX $modx, array $p): array
    {
        $where = [];

        $clause = TagFilter::clause(
            $modx,
            self::raw($p, 'tags', self::raw($p, 'tag')),
            (string) self::raw($p, 'tags_match', 'any')
        );
        if ($clause !== '') {
            $where[] = $clause;
        }

        $level = self::val($p, 'level');
        if ($level !== '') {
            $where['level'] = $level;
        }

        $processUid = self::val($p, 'process_uid');
        if ($processUid !== '') {
            $where['process_uid'] = $processUid;
        }

        $userId = self::raw($p, 'user_id');
        if ($userId !== null && $userId !== '') {
            $where['user_id'] = (int) $userId;
        }

        $class = self::val($p, 'class');
        if ($class !== '') {
            $where['class:LIKE'] = '%' . $class . '%';
        }

        $dateFrom = self::val($p, 'date_from');
        if ($dateFrom !== '' && ($tsFrom = strtotime($dateFrom))) {
            $where['createdon:>='] = $tsFrom;
        }
        $dateTo = self::val($p, 'date_to');
        if ($dateTo !== '' && ($tsTo = strtotime($dateTo))) {
            $where['createdon:<='] = $tsTo;
        }

        $query = self::val($p, 'query');
        if ($query !== '') {
            // Поиск по тексту: сообщение, источник (класс/метод), файл/строка.
            // Ищем и по отдельным колонкам, и по склеенным формам грида:
            // «class::function» и «file:line».
            $q = $modx->quote('%' . $query . '%');
            $where[] = '(' .
                'message LIKE ' . $q .
                ' OR class LIKE ' . $q .
                ' OR function LIKE ' . $q .
                ' OR file LIKE ' . $q .
                ' OR CAST(line AS CHAR) LIKE ' . $q .
                ' OR CONCAT(class, \'::\', function) LIKE ' . $q .
                ' OR CONCAT(file, \':\', line) LIKE ' . $q .
            ')';
        }

        // Пользователь / сессия / ip — отдельной группой (AND к остальным).
        // Пользователь: по user_id (если число) и по username (подзапрос к modUser).
        $ident = self::val($p, 'ident');
        if ($ident !== '') {
            $q = $modx->quote('%' . $ident . '%');
            $usersTable = $modx->getTableName(modUser::class);
            $conds = [
                'session_id LIKE ' . $q,
                'ip LIKE ' . $q,
                'user_id IN (SELECT id FROM ' . $usersTable . ' WHERE username LIKE ' . $q . ')',
            ];
            if (ctype_digit((string) $ident)) {
                $conds[] = 'user_id = ' . (int) $ident;
            }
            $where[] = '(' . implode(' OR ', $conds) . ')';
        }

        return $where;
    }

    /** Есть ли хотя бы один активный фильтр (т.е. очистка будет не полной). */
    public static function hasAny(modX $modx, array $p): bool
    {
        return self::build($modx, $p) !== [];
    }

    /** Значение свойства как обрезанная строка ('' если нет). */
    private static function val(array $p, string $k): string
    {
        return isset($p[$k]) ? trim((string) $p[$k]) : '';
    }

    /** Сырое значение свойства с дефолтом. */
    private static function raw(array $p, string $k, $default = null)
    {
        return $p[$k] ?? $default;
    }
}
