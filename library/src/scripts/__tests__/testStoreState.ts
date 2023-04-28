/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import { DeepPartial } from "redux";
import { ICoreStoreState } from "@library/redux/reducerRegistry";
import merge from "lodash/merge";
import { INITIAL_USERS_STATE } from "@library/features/users/userModel";
import getStore, { resetActionAC } from "@library/redux/getStore";
import { INITIAL_LOCALE_STATE } from "@library/locales/localeReducer";
import { INITIAL_THEME_STATE } from "@library/theming/themeReducer";
import { INITIAL_CONFIG_STATE } from "@library/config/configReducer";
import { UserFixture } from "@library/features/__fixtures__/User.fixture";
import { LoadStatus } from "@library/@types/api/core";

const DEFAULT_STATE: ICoreStoreState = {
    users: {
        ...INITIAL_USERS_STATE,
        current: {
            ...UserFixture.adminAsCurrent,
        },
        usersByID: {
            2: {
                status: LoadStatus.SUCCESS,
                data: UserFixture.createMockUser({ userID: 2 }),
            },
        },
    },
    theme: INITIAL_THEME_STATE,
    locales: INITIAL_LOCALE_STATE,
    config: INITIAL_CONFIG_STATE,
};

export function testStoreState(state: DeepPartial<ICoreStoreState>) {
    return merge({}, DEFAULT_STATE, state);
}
