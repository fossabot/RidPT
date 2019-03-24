<?php
/**
 * Created by PhpStorm.
 * User: Rhilip
 * Date: 2019/2/27
 * Time: 23:18
 * @var League\Plates\Template\Template $this
 *
 */




?>

<?= $this->layout('admin/layout') ?>

<?php $this->start('title') ?>Redis Keys<?php $this->end(); ?>

<?php $this->start('panel') ?>
<h1>Redis Keys Status</h1>
<p>Please input the search pattern of keys, or your can use the search suggest</p>

<hr>

<div>
    <div class="layui-form-item">
        <!--suppress HtmlUnknownTarget -->
        <form id="search_redis" class="form-inline" method="get" action="/admin/service">
            <div class="form-group">
                <?php $pattern = $pattern ?? ''; ?>
                <label><input name="provider" type="text" class="form-control" value="redis" style="display: none"></label>
                <label><input name="panel" type="text" class="form-control" value="keys" style="display: none"></label>
                <div class="layui-inline">
                    <label class="layui-form-label">Search Keys</label>
                    <div class="layui-input-inline" style="width: 300px">
                        <input name="pattern" type="text" class="layui-input" placeholder="<?= $pattern ?? '' ?>" value="<?= $pattern ?? '' ?>">
                    </div>
                </div>
                <div class="layui-inline">
                    <button type="submit" class="layui-btn layui-btn-normal"><i class="fas fa-search"></i> Search</button>
                    <button type="reset" class="layui-btn layui-btn-danger"><i class="fas fa-times"></i> Reset</button>
                </div>
            </div>
        </form>
    </div>
    <?php $suggent_pattern = ['*', 'SESSION:*', 'TORRENT:*', 'TRACKER:*', 'USER:*'] ?>
    <div id="suggest_pattern">Suggest Pattern :
        <?php foreach ($suggent_pattern as $pat): ?>
            <a href="javascript:void(0);" data-pat="<?= $pat ?>"><span class="layui-badge layui-bg-green"><?= $pat ?></span></a>&nbsp;&nbsp;
        <?php endforeach; ?>
    </div>
</div>

<?php if ($pattern != ''): ?>
    <hr>
    <div>
        <div class="pull-left">Keys matching <code><?= $pattern ?></code></div>
        <div class="pull-right">(<strong><?= $num_keys ?? 0 ?></strong> out of <strong><?= $dbsize ?? 0 ?></strong>
            matched)
        </div>
    </div>
    <table class="layui-table">
        <thead>
        <tr>
            <th class="text-right" style="width: 5%">#</th>
            <th class="text-center" style="width: 10%">Type</th>
            <th class="text-left">Key</th>
            <th class="" style="width: 5%"></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $index = 0;
        $type_dict = [
            \Redis::REDIS_STRING => 'String',
            \Redis::REDIS_LIST => 'List',
            \Redis::REDIS_HASH => 'Hash',
            \Redis::REDIS_SET => 'Set',
            \Redis::REDIS_ZSET => 'Zset'
        ];
        ?>
        <?php foreach ($keys as $key): ?>
            <tr>
                <td class="text-right"><?= $index + ($offset * $perpage) ?></td>
                <td class="text-center"><?= $type_dict[$types[$key]] ?></td>
                <td class="text-left">
                    <a href="/admin/service?provider=redis&panel=key&key=<?= $this->e($key) ?>"><?= $key ?></a>
                </td>
                <td class="text-right">
                    <form method="post">
                        <input type="hidden" name="action" value="delkey"/>
                        <input type="hidden" name="key" value="<?= $key ?>"/>
                        <button class="layui-btn layui-btn-sm layui-btn-danger" type="submit"
                                onclick="return confirm('Are you sure you want to delete this key? <?= $key ?>');">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php $this->end(); ?>

<?php $this->push('script') ?>
<script>
    layui.use(['jquery'], function () {
        let $ = layui.jquery;
        $('#suggest_pattern a').click(function () {
            let pat = $(this).attr('data-pat');
            $('input[name="pattern"]').val(pat);
            $('#search_redis').submit();
        })
    });
</script>
<?php $this->end() ?>
