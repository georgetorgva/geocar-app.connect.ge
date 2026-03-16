<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Admin\SiteMapModel;
use App\Models\Admin\PageModel;


class SiteMap extends Controller
{
    /** @todo Not yet implemented. */
    public function getOne($id)
    {
        return response([]);
    }

    /** @todo Not yet implemented. */
    public function index($id)
    {
        return response([]);
    }

    /**
     * Insert or update a sitemap menu node.
     *
     * On insert (no 'id' in request), immediately sets the new node's sort
     * position to its own id, then regenerates the menu cache once after both
     * writes complete. On update, upd() regenerates the cache internally.
     * Response is served from the cache — no extra DB query.
     *
     * @param  Request $request  All sitemap node fields; see SiteMapModel::upd().
     * @return \Illuminate\Http\Response  Full menu list from cache.
     */
    public function updMenu(Request $request)
    {
        $menuItem = new SiteMapModel();
        $idItem   = $menuItem->upd($request->all());

        if (!$request->id && $idItem) {
            $menuItem->updateSortAndNesting(['id' => $idItem, 'sort' => $idItem]);
        }

        return response($menuItem->generateMenuForSite());
    }

    /**
     * Re-sort the sitemap menu tree.
     *
     * Delegates sorting to SiteMapModel::sortMenu(), which performs a single
     * batch CASE WHEN UPDATE and rebuilds the menu cache. Response is served
     * from the cache — no extra DB query.
     *
     * @param  Request $request  Sort payload; see SiteMapModel::sortMenu().
     * @return \Illuminate\Http\Response  Full menu list from cache.
     */
    public function sortMenu(Request $request)
    {
        $menuItem = new SiteMapModel();
        $menuItem->sortMenu($request->all());

        return response($menuItem->generateMenuForSite());
    }

    /**
     * Set a menu node as the site home page.
     *
     * Delegates to SiteMapModel::setMenuHomePage(), which clears all existing
     * home-page flags and sets the selected node, then rebuilds the cache.
     * Response is served from the cache — no extra DB query.
     *
     * @param  Request $request  Must contain 'id' of the node to promote.
     * @return \Illuminate\Http\Response  Full menu list from cache.
     */
    public function setHomePage(Request $request)
    {
        $menuItem = new SiteMapModel();
        $menuItem->setMenuHomePage($request->all());

        return response($menuItem->generateMenuForSite());
    }

    /**
     * Delete a menu node and re-parent its children to root.
     *
     * Delegates to SiteMapModel::deleteMenuNode(), which removes the node,
     * re-parents orphaned children, and rebuilds the cache.
     * Response is served from the cache — no extra DB query.
     *
     * @param  Request $request  Must contain 'menuId' of the node to delete.
     * @return \Illuminate\Http\Response  Full menu list from cache.
     */
    public function deleteMenu(Request $request)
    {
        $menuItem = new SiteMapModel();
        $menuItem->deleteMenuNode($request->menuId);

        return response($menuItem->generateMenuForSite());
    }

    /**
     * Delete an image attached to a menu node.
     *
     * Removes the media record and its file from storage, then clears the
     * media field on the menu node. Returns the raw result from deleteMenuMedia().
     *
     * @param  Request $request  Must contain 'menuId' and 'field'.
     * @return \Illuminate\Http\Response
     */
    public function deleteMenuImage(Request $request)
    {
        $menuItem = new SiteMapModel();
        $ret = $menuItem->deleteMenuMedia(['menu_id' => $request->menuId, 'field' => $request->field]);

        return response($ret);
    }

    /**
     * Update settings for a specific content type.
     *
     * Persists key-value settings under the 'content_type_settings_{type}'
     * group, then returns the full list of registered content types.
     *
     * @param  Request $request  Must contain 'contentType' (string) and 'settings' (array).
     * @return \Illuminate\Http\Response  All content types with updated settings.
     */
    public function updContentTypeSettings(Request $request)
    {
        $pages = new PageModel();
        $this->updOptions("content_type_settings_{$request->contentType}", $request->settings);

        return response($pages->getContentTypes());
    }

    /**
     * Update global site configuration settings.
     *
     * Persists key-value settings under the 'site_configurations' group and
     * returns the updated settings list.
     *
     * @param  Request $request  Must contain 'settings' (array of key-value pairs).
     * @return \Illuminate\Http\Response  Updated site configuration settings.
     */
    public function updSiteConfigurations(Request $request)
    {
        $ret = $this->updOptions('site_configurations', $request->settings);

        return response($ret);
    }

    /**
     * Upsert a set of key-value settings for a given content group.
     *
     * Loads the existing settings for the group once, then for each incoming
     * key either updates the existing row or inserts a new one. Returns the
     * full updated settings list after all writes.
     *
     * Uses SiteMapModel (the sitemap / options table) as the backing store.
     *
     * -------------------------------------------------------------------------
     * PARAMS
     * -------------------------------------------------------------------------
     * @param  string  $contentGroup  The content_group discriminator (e.g. 'site_configurations').
     * @param  array   $settings      Flat key → value map of settings to persist.
     *
     * -------------------------------------------------------------------------
     * RETURN VALUE
     * -------------------------------------------------------------------------
     * @return array  Updated settings list for the given content group.
     */
    public function updOptions($contentGroup = '', $settings = [])
    {
        if (empty($settings) || !is_array($settings)) {
            return [];
        }

        $options     = new SiteMapModel();
        $oldSettings = $options->getListBy(['content_group' => $contentGroup]);

        // Index existing rows by key for O(1) lookup
        $oldByKey = array_column($oldSettings, null, 'key');

        foreach ($settings as $k => $v) {
            $upd          = $oldByKey[$k] ?? [];
            $upd['key']   = $k;
            $upd['value'] = $v;
            $options->upd($upd, $contentGroup);
        }

        return $options->getListBy(['content_group' => $contentGroup]);
    }

}
