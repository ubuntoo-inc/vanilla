/**
 * @author Maneesh Chiba <maneesh.chiba@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license Proprietary
 */

import React from "react";
import { CustomLayoutLabItem, configKeys } from "@dashboard/labs/CustomLayoutLabItem";
import { render } from "@testing-library/react";
import { TestReduxProvider } from "@library/__tests__/TestReduxProvider";
import { LoadStatus } from "@library/@types/api/core";
import "@testing-library/jest-dom/extend-expect";
import { stableObjectHash } from "@vanilla/utils";

describe("Custom Layout Lab Item", () => {
    it("Checkbox is disabled while loading", () => {
        const tree = render(
            <TestReduxProvider
                state={{
                    config: {
                        configsByLookupKey: {
                            [stableObjectHash(configKeys)]: {
                                status: LoadStatus.LOADING,
                            },
                            [stableObjectHash(["labs.*"])]: {
                                status: LoadStatus.SUCCESS,
                                data: {},
                            },
                        },
                    },
                }}
            >
                <CustomLayoutLabItem />
            </TestReduxProvider>,
        );
        const checkbox = tree.container.querySelector(`input[type="checkbox"]`);
        expect(checkbox).toBeDisabled();
    });
    it("Checkbox is disabled when custom layout is applied", () => {
        const tree = render(
            <TestReduxProvider
                state={{
                    config: {
                        configsByLookupKey: {
                            [stableObjectHash(configKeys)]: {
                                status: LoadStatus.SUCCESS,
                                data: {
                                    "customLayout.categoryList": false,
                                    "customLayout.discussionList": false,
                                    "customLayout.home": true,
                                },
                            },
                            [stableObjectHash(["labs.*"])]: {
                                status: LoadStatus.SUCCESS,
                                data: {},
                            },
                        },
                    },
                }}
            >
                <CustomLayoutLabItem />
            </TestReduxProvider>,
        );
        const checkbox = tree.container.querySelector(`input[type="checkbox"]`);
        expect(checkbox).toBeDisabled();
    });
    it("Checkbox is enabled when legacy layout is applied", () => {
        const tree = render(
            <TestReduxProvider
                state={{
                    config: {
                        configsByLookupKey: {
                            [stableObjectHash(configKeys)]: {
                                status: LoadStatus.SUCCESS,
                                data: {
                                    "customLayout.categoryList": false,
                                    "customLayout.discussionList": false,
                                    "customLayout.home": false,
                                },
                                [stableObjectHash(["labs.*"])]: {
                                    status: LoadStatus.SUCCESS,
                                    data: {},
                                },
                            },
                        },
                    },
                }}
            >
                <CustomLayoutLabItem />
            </TestReduxProvider>,
        );
        const checkbox = tree.container.querySelector(`input[type="checkbox"]`);
        expect(checkbox).not.toBeDisabled();
    });
});
