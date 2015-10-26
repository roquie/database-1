<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright 2013-2015 Marius Sarca
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace Opis\Database\ORM\Relation;

use Opis\Database\ORM\Relation;

class HasOne extends Relation
{
    public function hasMany()
    {
        return false;
    }
    
    public function getResult()
    {
        $this->query->where($this->getForeignKey())->is($this->owner->{$this->owner->getPrimaryKey()});
        
        return $this->query()
                    ->fetchClass(get_class($this->model), array(false))
                    ->first();
    }
}