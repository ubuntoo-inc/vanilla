/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import React from "react";
import { IFieldError } from "@library/@types/api/core";
import PasswordInput from "@library/forms/PasswordInput";
import InputBlock from "@library/forms/InputBlock";
import { IInputTextProps } from "@library/forms/InputTextBlock";
import { t } from "@vanilla/i18n";
import { dashboardClasses } from "@dashboard/forms/dashboardStyles";
import { useFormGroup } from "@dashboard/forms/DashboardFormGroupContext";

interface IProps extends IInputTextProps {
    errors?: IFieldError[];
    disabled?: boolean;
}

export function DashboardPasswordInput(props: IProps) {
    const { inputProps } = props;
    const { inputID } = useFormGroup();

    return (
        <div className={"input-wrap"}>
            <InputBlock errors={props.errors}>
                <PasswordInput
                    id={inputID}
                    showUnmask
                    onChange={inputProps?.onChange}
                    value={inputProps?.value}
                    aria-label={t(inputProps?.["aria-label"] ?? "Current Password")}
                    required
                    className={dashboardClasses().passwordinput}
                />
            </InputBlock>
        </div>
    );
}
