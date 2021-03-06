<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RecipeItemService;
use App\Services\ShowService;
use Bugsnag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;
use Webpatser\Uuid\Uuid;

use App\Models\Step;

/**
 * Class AuthController
 * @package App\Http\Controllers
 */
class ShowController extends Controller
{
    protected $showService;
    protected $recipeItemService;

    public function __construct(ShowService $showService, RecipeItemService $recipeItemService)
    {
        $this->showService = $showService;
        $this->recipeItemService = $recipeItemService;
    }


    /**
     * Creates a new show, this will also create the associated chat and other details to go with the show.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postShow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'title' => 'required|string',
            'description' => 'required|string',
            'runtime' => 'required|string',
            'show_image_header' => 'required|string',
            'calories' => 'required|string'
        ]);

        if ($validator->fails()) {
            abort(400, $validator->errors());
        }

        $properties = $request->all();

        $result = $this->showService->createShow($properties);
        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        $show = $result->show;

        return $show;
    }


    /**
     * Add a RecipeItem to a Show
     *
     * /show/{show_id}/item/{recipe_id}
     *
     * @param Request $request
     * @param $show_id
     * @param $
     * @return mixed
     */
    public function postShowRecipeItem($show_id, $recipe_id)
    {
        $result = $this->showService->getShow($show_id);

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        $show = $result->show;

        $item_result = $this->recipeItemService->getRecipeItem($recipe_id);

        if (!$item_result->success) {
            abort(400, "Unable to add recipe item.");
        }

        $show->recipeItems()->attach($item_result->recipe_item->id);

        //Refresh data by retrieving the show again
        $result = $this->showService->getShow($show_id);

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        return $result->show;
    }


    public function postShowStart($show_id)
    {
        $result = $this->showService->getShow($show_id);

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        $show = $result->show;

        $show->started = true;
        $show->save();

        return $show;
    }

    public function postShowFinished($show_id)
    {
        $result = $this->showService->getShow($show_id);

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        $show = $result->show;

        $show->finished = true;
        $show->save();

        return $show;
    }

    public function postShowStep(Request $request, $show_id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'required|string',
            'step_number' => 'required',
            'show_id' => 'required'
        ]);

        if ($validator->fails()) {
            abort(400, $validator->errors());
        }

        $result = $this->showService->getShow($show_id);

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        $properties = $request->all();

        $step = Step::create($properties);

        $result = $this->showService->getShow($show_id);

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        $show = $result->show;
        return $show;
    }


    /**
     * Get a show an all details
     *
     * /show/{show_id}/item/{recipe_id}
     *
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function getNextShow()
    {
        $result = $this->showService->getNextShow();

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        return $result->show;
    }


    /**
     * Get a specific show an all details
     *
     * /show/{show_id}/item/{recipe_id}
     *
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function getShow($show_id)
    {
        $result = $this->showService->getShow($show_id);

        if (!$result->success) {
            abort($result->status_code, $result->error);
        }

        return $result->show;
    }

    /**
     * Gets the current step of the show.
     *
     * @param integet $show_id ID of the show.
     *
     * @return Step
     */
    public function getCurrentStep($show_id)
    {
        $result = $this->showService->getShow($show_id);
        if (!$result->success) {
            abort($result->status_code, $result->error);
        }
        $show = $result->show;

        if (is_null($show->current_step_id)) {
            $show->current_step_id = $show->steps[0]->id;
            $show->save();
        }
        return $show->currentStep;
    }

    public function putNextStep($show_id)
    {
        $result = $this->showService->getShow($show_id);
        if (!$result->success) {
            abort($result->status_code, $result->error);
        }
        $show = $result->show;

        $next_step_number = $show->currentStep->step_number + 1;
        $next_step = Step::where('show_id', $show->id)->where('step_number', $next_step_number)->first();
        if (!is_null($next_step)) {
            $show->current_step_id = $next_step->id;
            $show->save();
            return $next_step;
        }
        return $show->currentStep;
    }
}
